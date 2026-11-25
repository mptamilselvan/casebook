<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Price;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
        Config::set('services.razorpay.key', 'rzp_test_example');
        Config::set('services.razorpay.secret', 'test-secret');
        Config::set('services.razorpay.webhook_secret', 'hook-secret');
    }

    public function test_applying_the_free_plan_does_not_call_razorpay(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        $user->assignPlan($basic, $basic->inrPrice());
        $free = Plan::query()->where('code', Plan::FREE)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('plans.checkout', $free))
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('plan_name', 'Free');

        $this->assertSame($free->id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'plan_id' => $free->id,
            'amount' => 0,
            'status' => Transaction::PAID,
            'currency' => 'INR',
        ]);
        Http::assertNothingSent();
    }

    public function test_a_paid_plan_starts_a_razorpay_subscription(): void
    {
        $this->fakeRazorpay();
        $user = User::factory()->create();
        $user->assignFreePlan();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('plans.checkout', $basic), ['interval' => Price::MONTHLY])
            ->assertOk()
            ->assertJsonPath('status', 'checkout')
            ->assertJsonPath('subscription_id', 'sub_test_1')
            ->assertJsonPath('interval', 'monthly')
            ->assertJsonPath('key', 'rzp_test_example')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('currency', 'INR');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'amount' => 100,
            'interval' => 'monthly',
            'status' => Transaction::PENDING,
            'razorpay_subscription_id' => 'sub_test_1',
        ]);
        $this->assertSame('plan_monthly_10000', $basic->inrPrice('monthly')->fresh()->razorpay_plan_id);
        $this->assertSame($user->plan_id, $user->fresh()->plan_id);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.razorpay.com/v1/subscriptions'
                && $request['total_count'] === 120
                && $request['plan_id'] === 'plan_monthly_10000';
        });
    }

    public function test_a_yearly_subscription_uses_the_yearly_price(): void
    {
        $this->fakeRazorpay();
        $user = User::factory()->create();
        $user->assignFreePlan();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('plans.checkout', $basic), ['interval' => Price::YEARLY])
            ->assertOk()
            ->assertJsonPath('interval', 'yearly')
            ->assertJsonPath('amount', 100000);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'amount' => 1000,
            'interval' => 'yearly',
            'status' => Transaction::PENDING,
        ]);
        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/v1/plans')
                && $request['period'] === 'yearly'
                && $request['item']['amount'] === 100000;
        });
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.razorpay.com/v1/subscriptions'
                && $request['total_count'] === 10;
        });
    }

    public function test_a_verified_payment_updates_the_user_plan(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'price_id' => $basic->inrPrice()->id,
            'plan_name' => 'Basic',
            'amount' => 100,
            'currency' => 'INR',
            'interval' => 'monthly',
            'status' => Transaction::PENDING,
            'razorpay_subscription_id' => 'sub_test_1',
            'email' => $user->email,
        ]);
        $signature = hash_hmac('sha256', 'pay_test_1|sub_test_1', 'test-secret');

        $this->actingAs($user)
            ->postJson(route('plans.verify'), [
                'razorpay_subscription_id' => 'sub_test_1',
                'razorpay_payment_id' => 'pay_test_1',
                'razorpay_signature' => $signature,
            ])
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('plan_name', 'Basic');

        $fresh = $user->fresh();
        $this->assertSame($basic->id, $fresh->plan_id);
        $this->assertSame('Basic', $fresh->plan_name);
        $this->assertDatabaseHas('transactions', [
            'razorpay_subscription_id' => 'sub_test_1',
            'razorpay_payment_id' => 'pay_test_1',
            'status' => Transaction::PAID,
        ]);
        $this->assertSame('monthly', $fresh->billing_interval);
        $this->assertSame('active', $fresh->subscription_status);
        $this->assertSame('sub_test_1', $fresh->razorpay_subscription_id);
    }

    public function test_a_bad_signature_leaves_the_plan_unchanged(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'price_id' => $basic->inrPrice()->id,
            'plan_name' => 'Basic',
            'amount' => 100,
            'currency' => 'INR',
            'interval' => 'monthly',
            'status' => Transaction::PENDING,
            'razorpay_subscription_id' => 'sub_test_1',
            'email' => $user->email,
        ]);

        $this->actingAs($user)
            ->postJson(route('plans.verify'), [
                'razorpay_subscription_id' => 'sub_test_1',
                'razorpay_payment_id' => 'pay_test_1',
                'razorpay_signature' => 'not-the-signature',
            ])
            ->assertUnprocessable();

        $this->assertSame($user->plan_id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('transactions', [
            'razorpay_subscription_id' => 'sub_test_1',
            'status' => Transaction::PENDING,
        ]);
    }

    public function test_the_webhook_marks_a_charged_subscription_as_paid(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();
        $pro = Plan::query()->where('code', Plan::PRO)->firstOrFail();
        Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'price_id' => $pro->inrPrice('yearly')->id,
            'plan_name' => 'Pro',
            'amount' => 3000,
            'currency' => 'INR',
            'interval' => 'yearly',
            'status' => Transaction::PENDING,
            'razorpay_subscription_id' => 'sub_hook_1',
            'email' => $user->email,
        ]);

        $this->postWebhook('subscription.charged', 'sub_hook_1', 'pay_hook_1', 1)
            ->assertOk()
            ->assertJsonPath('status', 'paid');

        $fresh = $user->fresh();
        $this->assertSame($pro->id, $fresh->plan_id);
        $this->assertSame('yearly', $fresh->billing_interval);
        $this->assertSame('sub_hook_1', $fresh->razorpay_subscription_id);
        $this->assertDatabaseHas('transactions', [
            'razorpay_subscription_id' => 'sub_hook_1',
            'razorpay_payment_id' => 'pay_hook_1',
            'method' => 'upi',
            'status' => Transaction::PAID,
        ]);
    }

    public function test_a_later_charge_renews_the_same_subscription(): void
    {
        $user = User::factory()->create();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        $price = $basic->inrPrice('monthly');
        $user->assignPlan($basic, $price, [
            'id' => 'sub_hook_1',
            'status' => 'active',
            'renews_on' => now()->toDateString(),
        ]);
        Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'price_id' => $price->id,
            'plan_name' => 'Basic',
            'amount' => 100,
            'currency' => 'INR',
            'interval' => 'monthly',
            'status' => Transaction::PAID,
            'razorpay_subscription_id' => 'sub_hook_1',
            'razorpay_payment_id' => 'pay_first',
            'email' => $user->email,
            'paid_at' => now()->subMonth(),
        ]);

        $this->postWebhook('subscription.charged', 'sub_hook_1', 'pay_renewal', 2)
            ->assertOk()
            ->assertJsonPath('status', 'renewed');

        $this->assertSame(2, Transaction::query()->where('razorpay_subscription_id', 'sub_hook_1')->where('status', Transaction::PAID)->count());
        $this->assertSame($basic->id, $user->fresh()->plan_id);
        $this->assertTrue($user->fresh()->plan_renews_on->greaterThan(now()));
    }

    public function test_cancelling_the_current_subscription_returns_the_free_plan(): void
    {
        $user = User::factory()->create();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        $user->assignPlan($basic, $basic->inrPrice('monthly'), [
            'id' => 'sub_hook_1',
            'status' => 'active',
        ]);

        $this->postWebhook('subscription.cancelled', 'sub_hook_1', null, 1)
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $fresh = $user->fresh();
        $this->assertSame('free', $fresh->plan->code);
        $this->assertNull($fresh->razorpay_subscription_id);
        $this->assertNull($fresh->billing_interval);
    }

    public function test_mobile_checkout_uses_the_same_payment_flow(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        $user->assignPlan($basic, $basic->inrPrice());
        $free = Plan::query()->where('code', Plan::FREE)->firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson('/api/plans/'.$free->id.'/checkout')
            ->assertOk()
            ->assertJsonPath('status', 'paid')
            ->assertJsonPath('user.plan_name', 'Free');
    }

    private function fakeRazorpay(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/v1/plans')) {
                return Http::response([
                    'id' => 'plan_'.$request['period'].'_'.$request['item']['amount'],
                ]);
            }

            if ($request->url() === 'https://api.razorpay.com/v1/subscriptions') {
                return Http::response(['id' => 'sub_test_1', 'status' => 'created']);
            }

            if (str_contains($request->url(), '/cancel')) {
                return Http::response(['status' => 'cancelled']);
            }

            return Http::response(['error' => ['description' => 'Unexpected Razorpay call']], 500);
        });
    }

    private function postWebhook(string $event, string $subscriptionId, ?string $paymentId, int $paidCount): TestResponse
    {
        $body = json_encode([
            'event' => $event,
            'payload' => [
                'subscription' => [
                    'entity' => [
                        'id' => $subscriptionId,
                        'status' => $event === 'subscription.charged' ? 'active' : 'cancelled',
                        'paid_count' => $paidCount,
                        'current_end' => now()->addMonth()->getTimestamp(),
                    ],
                ],
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'status' => 'captured',
                        'method' => 'upi',
                        'amount' => 10000,
                        'currency' => 'INR',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'hook-secret');

        return $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body);
    }
}
