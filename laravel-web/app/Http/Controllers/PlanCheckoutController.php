<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlanCheckoutController extends Controller
{
    public function checkout(Request $request, Plan $plan): JsonResponse
    {
        try {
            $payload = Billing::start($request->user(), $plan);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        if ($payload['status'] === 'paid' && $request->hasSession()) {
            $request->session()->flash('status', $payload['plan_name'].' is now your plan.');
        }

        return response()->json($payload);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $payload = Billing::verify(
            $request->user(),
            $data['razorpay_order_id'],
            $data['razorpay_payment_id'],
            $data['razorpay_signature'],
        );

        if ($request->hasSession()) {
            $request->session()->flash('status', $payload['plan_name'].' is now your plan.');
        }

        return response()->json($payload);
    }
}
