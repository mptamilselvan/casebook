<?php

namespace App\Http\Middleware;

use App\Enums\CaseMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCaseMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CaseMode::current() === null) {
            return redirect()->route('mode.create');
        }

        return $next($request);
    }
}
