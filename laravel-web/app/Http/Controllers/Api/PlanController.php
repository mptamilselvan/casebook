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

        return response()->json([
            'currency' => 'INR',
            'current_plan_id' => $request->user()->plan_id,
            'plans' => $plans->map(fn (Plan $plan) => $plan->catalog())->values(),
        ]);
    }
}
