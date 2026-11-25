<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Price;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => Plan::FREE, 'name' => 'Free', 'space_limit' => 1, 'cases_limit' => 10, 'sort_order' => 1, 'monthly' => 0, 'yearly' => 0],
            ['code' => Plan::BASIC, 'name' => 'Basic', 'space_limit' => 5, 'cases_limit' => 50, 'sort_order' => 2, 'monthly' => 100, 'yearly' => 1000],
            ['code' => Plan::PRO, 'name' => 'Pro', 'space_limit' => 10, 'cases_limit' => null, 'sort_order' => 3, 'monthly' => 300, 'yearly' => 3000],
        ];

        foreach ($plans as $row) {
            $plan = Plan::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'space_limit' => $row['space_limit'],
                    'cases_limit' => $row['cases_limit'],
                    'sort_order' => $row['sort_order'],
                ],
            );

            foreach ([Price::MONTHLY => $row['monthly'], Price::YEARLY => $row['yearly']] as $interval => $amount) {
                Price::query()->updateOrCreate(
                    ['plan_id' => $plan->id, 'currency' => Price::INR, 'interval' => $interval],
                    ['amount' => $amount],
                );
            }
        }

        User::query()->whereNull('plan_id')->each(function (User $user): void {
            $user->assignFreePlan();
        });
    }
}
