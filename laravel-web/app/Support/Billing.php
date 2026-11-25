<?php

namespace App\Support;

use App\Models\Plan;
use App\Models\Price;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

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
    public static function start(User $user, Plan $plan, string $interval = Price::MONTHLY): array
    {
        if (! in_array($interval, Price::INTERVALS, true)) {
            $interval = Price::MONTHLY;
        }

        $plan->loadMissing('prices');
        $price = $plan->inrPrice($interval);
        if ($price === null) {
            throw ValidationException::withMessages([
                'plan' => 'This plan does not have that billing period.',
            ]);
        }

        $alreadyActive = (int) $user->plan_id === (int) $plan->id
            && (int) $user->price_id === (int) $price->id
            && ($price->amount === 0 || $user->subscription_status === 'active');
        if ($alreadyActive) {
            throw ValidationException::withMessages([
                'plan' => 'You are already on the '.$plan->name.' plan.',
            ]);
        }

        if ($price->amount === 0) {
            $previous = $user->razorpay_subscription_id;
            $transaction = self::record($user, $plan, $price, Transaction::PAID);
            self::grant($transaction);
            self::cancelSubscription($previous, null);

            return self::paidPayload($user->fresh(), $plan);
        }

        if (! self::configured()) {
            throw new RuntimeException('Razorpay is not configured.');
        }

        $transaction = self::record($user, $plan, $price, Transaction::PENDING);
        try {
            $razorpayPlanId = self::razorpayPlanId($plan, $price);
        } catch (RuntimeException $exception) {
            $transaction->update(['status' => Transaction::FAILED]);

            throw $exception;
        }

        $response = Http::withBasicAuth(self::key(), (string) config('services.razorpay.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.razorpay.com/v1/subscriptions', [
                'plan_id' => $razorpayPlanId,
                'total_count' => $price->interval === Price::YEARLY ? 10 : 120,
                'quantity' => 1,
                'customer_notify' => true,
                'notes' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'price_id' => (string) $price->id,
                    'transaction_id' => (string) $transaction->id,
                ],
            ]);

        $subscriptionId = $response->json('id');
        if (! $response->successful() || ! is_string($subscriptionId)) {
            $transaction->update(['status' => Transaction::FAILED]);

            throw new RuntimeException(self::razorpayError($response->json('error.description'), 'Razorpay could not start this subscription.'));
        }

        $transaction->update(['razorpay_subscription_id' => $subscriptionId]);

        return [
            'status' => 'checkout',
            'key' => self::key(),
            'subscription_id' => $subscriptionId,
            'interval' => $price->interval,
            'amount' => $price->amount * 100,
            'currency' => Price::INR,
            'name' => 'Case Book',
            'description' => $plan->name.' · '.($price->interval === Price::YEARLY ? 'Yearly' : 'Monthly'),
            'email' => $user->email,
            'customer_name' => $user->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function verify(User $user, string $subscriptionId, string $paymentId, string $signature): array
    {
        $transaction = Transaction::query()
            ->where('user_id', $user->id)
            ->where('razorpay_subscription_id', $subscriptionId)
            ->first();

        if ($transaction === null) {
            throw ValidationException::withMessages([
                'razorpay_subscription_id' => 'This payment does not match an open subscription.',
            ]);
        }

        $expected = hash_hmac('sha256', $paymentId.'|'.$subscriptionId, (string) config('services.razorpay.secret'));
        if (! hash_equals($expected, $signature)) {
            throw ValidationException::withMessages([
                'razorpay_signature' => 'This payment could not be verified.',
            ]);
        }

        $transaction = self::activate($transaction, [
            'payment_id' => $paymentId,
            'signature' => $signature,
            'subscription_id' => $subscriptionId,
            'subscription_status' => 'active',
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

        $event = (string) ($payload['event'] ?? '');
        $subscription = $payload['payload']['subscription']['entity'] ?? null;
        $payment = $payload['payload']['payment']['entity'] ?? null;
        if (! is_array($payment)) {
            $payment = [];
        }

        if (is_array($subscription) && in_array($event, ['subscription.charged', 'subscription.activated'], true)) {
            return self::subscriptionCharged($subscription, $payment);
        }

        if (is_array($subscription) && in_array($event, ['subscription.cancelled', 'subscription.completed', 'subscription.halted'], true)) {
            return self::subscriptionEnded($subscription);
        }

        return self::legacyPayment($event, $payment);
    }

    /**
     * @param  array{payment_id?: string|null, signature?: string|null, method?: string|null, gateway?: array<string, mixed>|null, subscription_id?: string|null, subscription_status?: string|null, renews_on?: string|null}  $details
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
                'razorpay_subscription_id' => $details['subscription_id'] ?? $locked->razorpay_subscription_id,
                'method' => $details['method'] ?? $locked->method,
                'paid_at' => now(),
                'gateway' => $details['gateway'] ?? $locked->gateway,
            ])->save();

            self::grant($locked, $details);

            return $locked;
        });
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @param  array<string, mixed>  $payment
     */
    private static function subscriptionCharged(array $subscription, array $payment): string
    {
        $subscriptionId = $subscription['id'] ?? null;
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return 'ignored';
        }

        $paymentId = is_string($payment['id'] ?? null) ? $payment['id'] : null;
        if ($paymentId !== null && Transaction::query()->where('razorpay_payment_id', $paymentId)->exists()) {
            return 'paid';
        }

        $pending = Transaction::query()
            ->where('razorpay_subscription_id', $subscriptionId)
            ->where('status', Transaction::PENDING)
            ->first();
        $details = self::chargeDetails($subscription, $payment);

        if ($pending !== null) {
            self::activate($pending, $details);

            return 'paid';
        }

        $user = User::query()->where('razorpay_subscription_id', $subscriptionId)->first();
        if ($user === null) {
            return 'ignored';
        }

        $paidCount = (int) ($subscription['paid_count'] ?? 0);
        $recorded = Transaction::query()
            ->where('razorpay_subscription_id', $subscriptionId)
            ->where('status', Transaction::PAID)
            ->count();
        if ($paidCount > 0 && $recorded >= $paidCount) {
            return 'paid';
        }

        $plan = Plan::query()->with('prices')->find($user->plan_id);
        $price = $plan?->prices->firstWhere('id', $user->price_id) ?? $plan?->inrPrice($user->billing_interval);
        if ($plan === null || $price === null) {
            return 'ignored';
        }

        $transaction = self::record($user, $plan, $price, Transaction::PENDING);
        $transaction->update(['razorpay_subscription_id' => $subscriptionId]);
        self::activate($transaction, $details);

        return 'renewed';
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private static function subscriptionEnded(array $subscription): string
    {
        $subscriptionId = $subscription['id'] ?? null;
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return 'ignored';
        }

        $user = User::query()->where('razorpay_subscription_id', $subscriptionId)->first();
        $plan = Plan::query()->with('prices')->where('code', Plan::FREE)->first();
        $price = $plan?->inrPrice();
        if ($user === null || $plan === null || $price === null) {
            return 'ignored';
        }

        $user->assignPlan($plan, $price);

        return 'cancelled';
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private static function legacyPayment(string $event, array $payment): string
    {
        $orderId = $payment['order_id'] ?? null;
        if (! is_string($orderId) || $orderId === '') {
            return 'ignored';
        }

        $transaction = Transaction::query()->where('razorpay_order_id', $orderId)->first();
        if ($transaction === null) {
            return 'ignored';
        }

        $captured = $event === 'payment.captured'
            || $event === 'order.paid'
            || ($payment['status'] ?? null) === 'captured';

        if ($captured) {
            self::activate($transaction, [
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
     * @param  array{payment_id?: string|null, signature?: string|null, method?: string|null, gateway?: array<string, mixed>|null, subscription_id?: string|null, subscription_status?: string|null, renews_on?: string|null}  $details
     */
    private static function activate(Transaction $transaction, array $details): Transaction
    {
        $previous = User::query()->find($transaction->user_id)?->razorpay_subscription_id;
        $paid = self::markPaid($transaction, $details);
        self::cancelSubscription($previous, $paid->razorpay_subscription_id);

        return $paid;
    }

    private static function razorpayPlanId(Plan $plan, Price $price): string
    {
        if (filled($price->razorpay_plan_id)) {
            return (string) $price->razorpay_plan_id;
        }

        $response = Http::withBasicAuth(self::key(), (string) config('services.razorpay.secret'))
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.razorpay.com/v1/plans', [
                'period' => $price->interval,
                'interval' => 1,
                'item' => [
                    'name' => $plan->name.' '.($price->interval === Price::YEARLY ? 'yearly' : 'monthly'),
                    'amount' => $price->amount * 100,
                    'currency' => Price::INR,
                    'description' => $plan->name.' billed every '.$price->period(),
                ],
                'notes' => [
                    'plan_id' => (string) $plan->id,
                    'price_id' => (string) $price->id,
                ],
            ]);

        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id)) {
            throw new RuntimeException(self::razorpayError($response->json('error.description'), 'Razorpay could not start this subscription.'));
        }

        $price->update(['razorpay_plan_id' => $id]);

        return $id;
    }

    private static function cancelSubscription(?string $subscriptionId, ?string $keepId): void
    {
        if (! is_string($subscriptionId) || $subscriptionId === '' || $subscriptionId === $keepId || ! self::configured()) {
            return;
        }

        try {
            Http::withBasicAuth(self::key(), (string) config('services.razorpay.secret'))
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.razorpay.com/v1/subscriptions/'.$subscriptionId.'/cancel', [
                    'cancel_at_cycle_end' => false,
                ]);
        } catch (Throwable) {
            // The new plan is already saved. A failed cancel must not undo it.
        }
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
            'interval' => $price->interval,
            'status' => $status,
            'email' => $user->email,
            'paid_at' => $status === Transaction::PAID ? now() : null,
        ]);
    }

    /**
     * @param  array{subscription_id?: string|null, subscription_status?: string|null, renews_on?: string|null}  $details
     */
    private static function grant(Transaction $transaction, array $details = []): void
    {
        $plan = Plan::query()->with('prices')->find($transaction->plan_id);
        $price = $plan?->prices->firstWhere('id', $transaction->price_id) ?? $plan?->inrPrice($transaction->interval);
        $user = User::query()->find($transaction->user_id);
        if ($plan === null || $price === null || $user === null) {
            return;
        }

        $subscriptionId = $details['subscription_id'] ?? $transaction->razorpay_subscription_id;
        $user->assignPlan($plan, $price, [
            'id' => is_string($subscriptionId) ? $subscriptionId : null,
            'status' => $details['subscription_status'] ?? ($price->amount > 0 ? 'active' : null),
            'renews_on' => $details['renews_on'] ?? self::renewsOn($price, []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $subscription
     * @param  array<string, mixed>  $payment
     * @return array{payment_id?: string|null, method?: string|null, gateway?: array<string, mixed>, subscription_id: string, subscription_status: string, renews_on?: string|null}
     */
    private static function chargeDetails(array $subscription, array $payment): array
    {
        $price = null;

        return [
            'payment_id' => is_string($payment['id'] ?? null) ? $payment['id'] : null,
            'method' => is_string($payment['method'] ?? null) ? $payment['method'] : null,
            'gateway' => $payment === [] ? null : self::gatewayDetails($payment),
            'subscription_id' => is_string($subscription['id'] ?? null) ? $subscription['id'] : null,
            'subscription_status' => is_string($subscription['status'] ?? null) ? $subscription['status'] : 'active',
            'renews_on' => self::renewsOn($price, $subscription),
        ];
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private static function renewsOn(?Price $price, array $subscription): ?string
    {
        $end = $subscription['current_end'] ?? null;
        if (is_numeric($end) && (int) $end > 0) {
            return Carbon::createFromTimestamp((int) $end)->toDateString();
        }

        if ($price === null || $price->amount === 0) {
            return null;
        }

        return $price->interval === Price::YEARLY
            ? now()->addYear()->toDateString()
            : now()->addMonth()->toDateString();
    }

    private static function razorpayError(mixed $description, string $fallback): string
    {
        return is_string($description) && $description !== '' ? $description : $fallback;
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
            'subscription_id' => $payment['subscription_id'] ?? null,
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
