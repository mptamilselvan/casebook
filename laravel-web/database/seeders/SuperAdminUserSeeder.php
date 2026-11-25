<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminUserSeeder extends Seeder
{
    public const EMAIL = 'superadmin@casebook.test';

    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'superadmin',
                'password' => 'password',
                'role' => User::ROLE_SUPERADMIN,
                'email_verified_at' => now(),
            ],
        );
        $admin->assignFreePlan();
    }
}
