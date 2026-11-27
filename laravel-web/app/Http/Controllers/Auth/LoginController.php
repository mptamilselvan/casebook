<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\EmailLogin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => EmailLogin::RULES,
        ], EmailLogin::MESSAGES);

        $result = EmailLogin::resolve($validated['email']);

        if ($result['disabled']) {
            return back()->withErrors([
                'email' => 'This account is disabled.',
            ]);
        }

        if ($result['user']->isSuperAdmin()) {
            return back()->withErrors([
                'email' => 'Superadmin accounts sign in from the admin page.',
            ]);
        }

        if ($result['verified']) {
            Auth::login($result['user']);
            $request->session()->regenerate();
            $request->session()->forget('case_mode');

            return redirect()->intended(route('cases.index'));
        }

        return back()->with('status', 'We sent a verification link to '.$result['user']->email.'. Open it to continue.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
