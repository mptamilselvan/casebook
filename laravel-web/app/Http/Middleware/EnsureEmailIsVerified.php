<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasVerifiedEmail()) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->regenerate();

        return redirect()
            ->route('login')
            ->with('status', 'Verify your email before opening Case Book.');
    }
}
