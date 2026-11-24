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

        return view('casebook.plans', [
            'plans' => $plans,
            'currentPlanId' => $request->user()->plan_id,
        ]);
    }
}
