<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('Auth/ChangePassword', [
            'forced' => $user->must_change_password,
            'homeUrl' => $this->homeUrl($user),
            'changedAt' => $user->password_changed_at?->translatedFormat('j F Y'),
        ]);
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ])->save();

        $request->session()->regenerate();

        return redirect($this->homeUrl($user))->with('status', 'Votre mot de passe a été mis à jour.');
    }

    private function homeUrl(User $user): string
    {
        return $user->isAdmin() ? route('dashboard') : route('punch.create');
    }
}
