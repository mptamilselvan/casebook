<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class EmailLogin
{
    public const RULES = ['required', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'email', 'max:255'];

    public const MESSAGES = [
        'email.regex' => 'Enter an email with a dot after the @.',
    ];

    /**
     * @return array{user: User, verified: bool}
     */
    public static function resolve(string $email): array
    {
        $email = Str::lower($email);
        $user = User::query()->where('email', $email)->first();

        if ($user?->hasVerifiedEmail()) {
            return ['user' => $user, 'verified' => true];
        }

        if (! $user) {
            $user = User::query()->create([
                'name' => self::nameFromEmail($email),
                'email' => $email,
                'password' => Str::password(40),
            ]);
        }

        $user->sendEmailVerificationNotification();

        return ['user' => $user, 'verified' => false];
    }

    public static function markVerified(User $user, string $hash): void
    {
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'This verification link is invalid or expired.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
    }

    public static function nameFromEmail(string $email): string
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
