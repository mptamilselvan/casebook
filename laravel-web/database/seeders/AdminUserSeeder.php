<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@casebook.test';

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );
        $admin->assignFreePlan();
    }
}
