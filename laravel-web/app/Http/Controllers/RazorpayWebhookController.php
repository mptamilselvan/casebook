<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $status = Billing::handleWebhook(
            $request->getContent(),
            $request->header('X-Razorpay-Signature'),
        );

        return response()->json(['status' => $status]);
    }
}
