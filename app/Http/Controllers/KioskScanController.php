<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page after scanning the reception QR: staff punch or customer review.
 */
class KioskScanController extends Controller
{
    public function show(Request $request, Kiosk $kiosk): Response|RedirectResponse
    {
        abort_unless($kiosk->isOpen(), 404);

        $code = $request->string('code')->limit(255, '')->toString();
        $punchUrl = $code === ''
            ? route('punch.create')
            : route('punch.create', ['code' => $code]);

        $user = $request->user();

        if ($user instanceof User && $user->isStaff()) {
            return redirect()->to($punchUrl);
        }

        return Inertia::render('Kiosk/Scan', [
            'kioskName' => $kiosk->name,
            'shopName' => $kiosk->shop?->name,
            'punchUrl' => $punchUrl,
            'reviewUrl' => route('kiosk.reviews.show', $kiosk),
        ]);
    }
}
