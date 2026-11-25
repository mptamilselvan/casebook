<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $plans = Plan::query()
            ->with('prices')
            ->orderBy('sort_order')
            ->get();
        $user = $request->user();

        return view('casebook.plans', [
            'plans' => $plans,
            'currentPlanId' => $user->plan_id,
            'billingInterval' => $user->billing_interval,
            'subscriptionStatus' => $user->subscription_status,
            'renewsOn' => $user->plan_renews_on?->format('j M Y'),
        ]);
    }
}
