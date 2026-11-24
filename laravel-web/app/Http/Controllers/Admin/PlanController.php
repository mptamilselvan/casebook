<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Price;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::query()
            ->with('prices')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.plans.index', [
            'plans' => $plans,
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $input = $request->all();
        $input['cases_limit'] = $request->input('cases_limit') === '' || $request->input('cases_limit') === null
            ? null
            : $request->input('cases_limit');

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'space_limit' => ['required', 'integer', 'min:0', 'max:100000'],
            'cases_limit' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'amount' => ['required', 'integer', 'min:0', 'max:10000000'],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('editing_plan', $plan->id);
        }

        $data = $validator->validated();

        $plan->update([
            'name' => $data['name'],
            'space_limit' => $data['space_limit'],
            'cases_limit' => $data['cases_limit'],
        ]);

        Price::query()->updateOrCreate(
            ['plan_id' => $plan->id, 'currency' => Price::INR],
            ['amount' => $data['amount']],
        );

        User::query()
            ->where('plan_id', $plan->id)
            ->update(['plan_name' => $plan->name]);

        return back()->with('status', $plan->name.' updated.');
    }
}
