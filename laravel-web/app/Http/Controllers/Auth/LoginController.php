<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
            'email' => ['required', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'email', 'max:255'],
        ], [
            'email.regex' => 'Enter an email with a dot after the @.',
        ]);

        $email = Str::lower($validated['email']);
        $user = User::query()->where('email', $email)->first();

        if ($user?->hasVerifiedEmail()) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('cases.index'));
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => $this->nameFromEmail($email),
                'email' => $email,
                'password' => Str::password(40),
            ]);
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'We sent a verification link to '.$email.'. Open it to continue.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function nameFromEmail(string $email): string
    {
        $name = Str::of($email)
            ->before('@')
            ->replace(['.', '_', '-'], ' ')
            ->squish()
            ->title()
            ->toString();

        return $name !== '' ? $name : 'Advocate';
    }
}
