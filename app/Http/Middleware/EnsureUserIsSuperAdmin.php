<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSuperAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin() !== true) {
            abort_unless($request->isMethod('GET') && ! $request->expectsJson(), 403);

            return redirect()->route('dashboard')->with('status', 'Cette page est réservée au super administrateur.');
        }

        return $next($request);
    }
}
