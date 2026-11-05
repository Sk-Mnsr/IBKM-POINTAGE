<?php

namespace App\Http\Controllers;

use App\Actions\RecordPunch;
use App\Enums\PunchType;
use App\Http\Requests\IdentifyAtKioskRequest;
use App\Http\Requests\StoreKioskPunchRequest;
use App\Models\Kiosk;
use App\Models\Punch;
use App\Models\User;
use App\Support\AttendanceDay;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Punching on the tablet itself with a phone number and password, for staff who have no phone.
 * The employee is never signed in on the tablet: a short-lived ticket links the two steps instead.
 */
class KioskPunchController extends Controller
{
    private const int TICKET_SECONDS = 120;

    private const int MAX_ATTEMPTS = 5;

    public function identify(IdentifyAtKioskRequest $request, Kiosk $kiosk): JsonResponse
    {
        $this->ensureCanOperate($request, $kiosk);

        $phone = $request->string('phone')->toString();
        $throttleKey = 'kiosk-login:'.$kiosk->id.'|'.$phone;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'phone' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($throttleKey).' secondes.',
            ]);
        }

        $employee = User::query()->staff()->where('phone', $phone)->first();

        if ($employee === null || ! Hash::check($request->string('password')->toString(), $employee->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'phone' => 'Téléphone ou mot de passe incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        if (! $employee->is_active) {
            throw ValidationException::withMessages(['phone' => 'Ce compte est désactivé.']);
        }

        if ($employee->shop_id !== $kiosk->shop_id) {
            throw ValidationException::withMessages(['phone' => 'Ce compte n’appartient pas à cette boutique.']);
        }

        return response()->json([
            'ticket' => $this->issueTicket($employee, $kiosk),
            'expiresIn' => self::TICKET_SECONDS,
            'givenName' => $employee->givenName(),
            'actions' => array_map(
                fn (PunchType $type): array => $type->toArray(),
                AttendanceDay::for($employee)->allowedTypes(),
            ),
            'today' => Punch::query()
                ->where('user_id', $employee->id)
                ->whereDate('punched_at', today())
                ->orderBy('punched_at')
                ->get()
                ->map(fn (Punch $punch): array => ['label' => $punch->type->label(), 'time' => $punch->punched_at->format('H:i')])
                ->all(),
        ]);
    }

    public function store(StoreKioskPunchRequest $request, Kiosk $kiosk, RecordPunch $recordPunch): JsonResponse
    {
        $this->ensureCanOperate($request, $kiosk);

        $ticket = $this->readTicket($request->string('ticket')->toString(), $kiosk);
        $employee = User::query()->staff()->where('is_active', true)->find($ticket['user']);

        if ($employee === null) {
            throw $this->expiredTicket();
        }

        $punch = $recordPunch->handleAtKiosk($employee, $kiosk, PunchType::from($request->string('type')->toString()));

        Cache::put('kiosk-ticket:'.$ticket['nonce'], true, self::TICKET_SECONDS * 2);

        return response()->json([
            'id' => $punch->id,
            'type' => $punch->type->value,
            'message' => $punch->type->confirmation($employee->givenName()),
            'label' => $punch->type->label(),
            'time' => $punch->punched_at->format('H:i'),
        ], 201);
    }

    private function ensureCanOperate(Request $request, Kiosk $kiosk): void
    {
        abort_unless($kiosk->isOpen(), 404);
        abort_unless($kiosk->canBeOperatedFrom($request), 403);
    }

    private function issueTicket(User $employee, Kiosk $kiosk): string
    {
        return Crypt::encryptString(json_encode([
            'user' => $employee->id,
            'kiosk' => $kiosk->id,
            'expires' => now()->addSeconds(self::TICKET_SECONDS)->getTimestamp(),
            'nonce' => Str::random(32),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * A ticket is valid for one punch, on the kiosk that issued it, for a couple of minutes.
     *
     * @return array{user: int, kiosk: int, expires: int, nonce: string}
     */
    private function readTicket(string $value, Kiosk $kiosk): array
    {
        try {
            $ticket = json_decode(Crypt::decryptString($value), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw $this->expiredTicket();
        }

        $isValid = is_array($ticket)
            && ($ticket['kiosk'] ?? null) === $kiosk->id
            && ($ticket['expires'] ?? 0) >= now()->getTimestamp()
            && is_string($ticket['nonce'] ?? null)
            && ! Cache::has('kiosk-ticket:'.$ticket['nonce']);

        if (! $isValid) {
            throw $this->expiredTicket();
        }

        return $ticket;
    }

    private function expiredTicket(): ValidationException
    {
        return ValidationException::withMessages([
            'ticket' => 'Votre session a expiré. Saisissez à nouveau votre téléphone et votre mot de passe.',
        ]);
    }
}
