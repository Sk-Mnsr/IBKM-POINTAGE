<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Sends users whose password was set by the manager to the password page before anything else.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            if ($request->expectsJson()) {
                abort(403, 'Vous devez d\'abord choisir un nouveau mot de passe.');
            }

            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
