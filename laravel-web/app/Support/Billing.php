<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Price;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Billing
{
    public static function configured(): bool
    {
        return filled(config('services.razorpay.key')) && filled(config('services.razorpay.secret'));
    }

    public static function key(): string
    {
        return (string) config('services.razorpay.key');
    }

    /**
     * @return array<string, mixed>
     */
    public static function start(User $user, Plan $plan): array
    {
        $plan->loadMissing('prices');
        $price = $plan->inrPrice();
        if ($price === null) {
            throw ValidationException::withMessages([
                'plan' => 'This plan does not have an INR price.',
            ]);
        }

        if ((int) $user->plan_id === (int) $plan->id) {
            throw ValidationException::withMessages([
                'plan' => 'You are already on the '.$plan->name.' plan.',
            ]);
        }

        if ($price->amount === 0) {
            $transaction = self::record($user, $plan, $price, Transaction::PAID);
            self::grant($transaction);

            return self::paidPayload($user->fresh(), $plan);
        }

        if (! self::configured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $transaction = self::record($user, $plan, $price, Transaction::PENDING);
        $paise = $price->amount * 100;
        $response = Http::withBasicAuth(self::key(), (string) config('services.razorpay.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $paise,
                'currency' => Price::INR,
                'receipt' => 'casebook-'.$transaction->id,
                'notes' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'transaction_id' => (string) $transaction->id,
                ],
            ]);

        if (! $response->successful() || ! is_string($response->json('id'))) {
            $transaction->update(['status' => Transaction::FAILED]);

            throw new RuntimeException('Razorpay could not start this payment.');
        }

        $transaction->update(['razorpay_order_id' => $response->json('id')]);

        return [
            'status' => 'checkout',
            'key' => self::key(),
            'order_id' => $transaction->razorpay_order_id,
            'amount' => $paise,
            'currency' => Price::INR,
            'name' => 'Case Book',
            'description' => $plan->name.' plan',
            'email' => $user->email,
            'customer_name' => $user->name,
        ];
    }

    public static function verify(User $user, string $orderId, string $paymentId, string $signature): array
    {
        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('razorpay_order_id', $orderId)
            ->first();

        if ($transaction === null) {
            throw ValidationException::withMessages([
                'razorpay_order_id' => 'This payment does not match an open order.',
            ]);
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, (string) config('services.razorpay.secret'));
        if (! hash_equals($expected, $signature)) {
            throw ValidationException::withMessages([
                'razorpay_signature' => 'This payment could not be verified.',
            ]);
        }

        $transaction = self::markPaid($transaction, [
            'payment_id' => $paymentId,
            'signature' => $signature,
        ]);
        $plan = Plan::query()->find($transaction->plan_id);

        return self::paidPayload($user->fresh(), $plan);
    }

    public static function handleWebhook(string $raw, ?string $signature): string
    {
        $secret = (string) (config('services.razorpay.webhook_secret') ?: config('services.razorpay.secret'));
        $expected = hash_hmac('sha256', $raw, $secret);
        if ($secret === '' || ! is_string($signature) || ! hash_equals($expected, $signature)) {
            abort(400, 'Invalid Razorpay signature.');
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return 'ignored';
        }

        $payment = $payload['payload']['payment']['entity'] ?? null;
        if (! is_array($payment)) {
            return 'ignored';
        }

        $orderId = $payment['order_id'] ?? null;
        if (! is_string($orderId) || $orderId === '') {
            return 'ignored';
        }

        $transaction = Transaction::query()->where('razorpay_order_id', $orderId)->first();
        if ($transaction === null) {
            return 'ignored';
        }

        $event = (string) ($payload['event'] ?? '');
        $captured = $event === 'payment.captured'
            || $event === 'order.paid'
            || ($payment['status'] ?? null) === 'captured';

        if ($captured) {
            self::markPaid($transaction, [
                'payment_id' => is_string($payment['id'] ?? null) ? $payment['id'] : null,
                'method' => is_string($payment['method'] ?? null) ? $payment['method'] : null,
                'gateway' => self::gatewayDetails($payment),
            ]);

            return 'paid';
        }

        if ($event === 'payment.failed' && $transaction->status === Transaction::PENDING) {
            $transaction->update([
                'status' => Transaction::FAILED,
                'razorpay_payment_id' => is_string($payment['id'] ?? null) ? $payment['id'] : $transaction->razorpay_payment_id,
                'method' => is_string($payment['method'] ?? null) ? $payment['method'] : null,
                'gateway' => self::gatewayDetails($payment),
            ]);

            return 'failed';
        }

        return 'ignored';
    }

    /**
     * @param  array{payment_id?: string|null, signature?: string|null, method?: string|null, gateway?: array<string, mixed>|null}  $details
     */
    public static function markPaid(Transaction $transaction, array $details): Transaction
    {
        return DB::transaction(function () use ($transaction, $details) {
            $locked = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Transaction::PAID) {
                return $locked;
            }

            $locked->forceFill([
                'status' => Transaction::PAID,
                'razorpay_payment_id' => $details['payment_id'] ?? $locked->razorpay_payment_id,
                'razorpay_signature' => $details['signature'] ?? $locked->razorpay_signature,
                'method' => $details['method'] ?? $locked->method,
                'paid_at' => now(),
                'gateway' => $details['gateway'] ?? $locked->gateway,
            ])->save();

            self::grant($locked);

            return $locked;
        });
    }

    private static function record(User $user, Plan $plan, Price $price, string $status): Transaction
    {
        return Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'price_id' => $price->id,
            'plan_name' => $plan->name,
            'amount' => $price->amount,
            'currency' => Price::INR,
            'status' => $status,
            'email' => $user->email,
            'paid_at' => $status === Transaction::PAID ? now() : null,
        ]);
    }

    private static function grant(Transaction $transaction): void
    {
        $plan = Plan::query()->with('prices')->find($transaction->plan_id);
        $price = $plan?->prices->firstWhere('id', $transaction->price_id) ?? $plan?->inrPrice();
        $user = User::query()->find($transaction->user_id);
        if ($plan === null || $price === null || $user === null) {
            return;
        }

        $user->assignPlan($plan, $price);
    }

    /**
     * @return array<string, mixed>
     */
    private static function paidPayload(User $user, ?Plan $plan): array
    {
        return [
            'status' => 'paid',
            'plan_name' => $plan?->name ?? $user->plan_name,
            'user' => CasebookData::user($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private static function gatewayDetails(array $payment): array
    {
        $card = is_array($payment['card'] ?? null) ? $payment['card'] : [];

        return array_filter([
            'id' => $payment['id'] ?? null,
            'order_id' => $payment['order_id'] ?? null,
            'amount' => $payment['amount'] ?? null,
            'currency' => $payment['currency'] ?? null,
            'status' => $payment['status'] ?? null,
            'method' => $payment['method'] ?? null,
            'bank' => $payment['bank'] ?? null,
            'wallet' => $payment['wallet'] ?? null,
            'vpa' => $payment['vpa'] ?? null,
            'email' => $payment['email'] ?? null,
            'contact' => $payment['contact'] ?? null,
            'card_network' => $card['network'] ?? null,
            'card_last4' => $card['last4'] ?? null,
            'card_type' => $card['type'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
