<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use App\Models\Punch;
use App\Support\KioskToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class KioskDisplayController extends Controller
{
    public function __construct(private KioskToken $tokens) {}

    public function show(Request $request, Kiosk $kiosk): Response
    {
        abort_unless($kiosk->is_active, 404);

        if (! $this->canOperate($request, $kiosk)) {
            $request->session()->put('url.intended', route('kiosk.show', $kiosk));

            return Inertia::render('Kiosk/Unpaired', [
                'kioskName' => $kiosk->name,
            ]);
        }

        $issued = $this->tokens->issue($kiosk);

        return Inertia::render('Kiosk/Show', [
            'kioskName' => $kiosk->name,
            'kioskId' => $kiosk->public_id,
            'token' => $issued['token'],
            'expiresIn' => $issued['expires_in'],
            'windowSeconds' => $this->tokens->windowSeconds(),
            'tokenUrl' => route('kiosk.token', $kiosk),
            'tokensUrl' => route('kiosk.tokens', $kiosk),
            'activityUrl' => route('kiosk.activity', $kiosk),
            'scanUrl' => route('punch.create'),
            'lastPunchId' => (int) $kiosk->punches()->max('id'),
            'isPaired' => $this->isPaired($request, $kiosk),
            'pairUrl' => route('kiosk.pair', $kiosk),
        ]);
    }

    public function token(Request $request, Kiosk $kiosk): JsonResponse
    {
        $this->ensureCanOperate($request, $kiosk);

        return response()->json($this->tokens->issue($kiosk));
    }

    /**
     * Codes for the coming hours, kept by the tablet in case the connection drops.
     */
    public function tokens(Request $request, Kiosk $kiosk): JsonResponse
    {
        $this->ensureCanOperate($request, $kiosk);

        $hours = (int) config('pointage.kiosk_offline_hours');
        $tokens = $this->tokens->batch($kiosk, $hours);

        return response()->json([
            'window_seconds' => $this->tokens->windowSeconds(),
            'server_time' => (int) floor(microtime(true) * 1000),
            'tokens' => $tokens,
        ]);
    }

    /**
     * Recent punches made on this kiosk, so the tablet can greet the employee by first name.
     */
    public function activity(Request $request, Kiosk $kiosk): JsonResponse
    {
        $this->ensureCanOperate($request, $kiosk);

        $punches = $kiosk->punches()
            ->with('user:id,name')
            ->where('id', '>', $request->integer('apres'))
            ->where('punched_at', '>=', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(5)
            ->get();

        return response()->json([
            'punches' => $punches->map(fn (Punch $punch): array => [
                'id' => $punch->id,
                'type' => $punch->type->value,
                'label' => $punch->type->label(),
                'message' => $punch->type->confirmation($punch->user->givenName()),
                'time' => $punch->punched_at->format('H:i'),
            ])->all(),
        ]);
    }

    public function pair(Kiosk $kiosk): RedirectResponse
    {
        abort_unless($kiosk->is_active, 404);

        Cookie::queue(Cookie::forever($kiosk->pairingCookieName(), $kiosk->pairingCookieValue()));

        return redirect()
            ->route('kiosk.show', $kiosk)
            ->with('status', 'Cette tablette est appairée. Vous pouvez vous déconnecter : la borne restera accessible.');
    }

    /**
     * Rotating the secret also voids the codes already stored on the tablets for offline use.
     */
    public function unpair(Kiosk $kiosk): RedirectResponse
    {
        $kiosk->forceFill([
            'secret' => Str::random(40),
            'pairing_version' => $kiosk->pairing_version + 1,
        ])->save();

        Cookie::queue(Cookie::forget($kiosk->pairingCookieName()));

        return redirect()
            ->route('dashboard')
            ->with('status', "Toutes les tablettes de la borne {$kiosk->name} ont été désappairées.");
    }

    private function ensureCanOperate(Request $request, Kiosk $kiosk): void
    {
        abort_unless($kiosk->is_active, 404);
        abort_unless($this->canOperate($request, $kiosk), 403);
    }

    private function canOperate(Request $request, Kiosk $kiosk): bool
    {
        return $request->user()?->isAdmin() === true || $this->isPaired($request, $kiosk);
    }

    private function isPaired(Request $request, Kiosk $kiosk): bool
    {
        $value = $request->cookie($kiosk->pairingCookieName());

        return is_string($value) && hash_equals($kiosk->pairingCookieValue(), $value);
    }
}
