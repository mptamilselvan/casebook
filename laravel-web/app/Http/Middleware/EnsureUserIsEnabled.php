<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_enabled) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'This account is disabled.',
            ], 403);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $route = $request->is('admin') || $request->is('admin/*') ? 'admin.login' : 'login';

        return redirect()
            ->route($route)
            ->withErrors(['email' => 'This account is disabled.']);
    }
}
