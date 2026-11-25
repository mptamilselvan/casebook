<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->with('prices')
            ->orderBy('sort_order')
            ->get();

        $user = $request->user();

        return response()->json([
            'currency' => 'INR',
            'current_plan_id' => $user->plan_id,
            'current_interval' => $user->billing_interval,
            'current_subscription_status' => $user->subscription_status,
            'plans' => $plans->map(fn (Plan $plan) => $plan->catalog())->values(),
        ]);
    }
}
