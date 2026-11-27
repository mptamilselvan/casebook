<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IndiaLocationSeeder::class,
            PlanSeeder::class,
            PermissionSeeder::class,
            SuperAdminUserSeeder::class,
            SearchIndexSeeder::class,
        ]);
    }
}
