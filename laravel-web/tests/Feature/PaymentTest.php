<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

    public function test_a_paid_plan_starts_a_razorpay_order(): void
    {
        Http::fake([
            'https://api.razorpay.com/v1/orders' => Http::response([
                'id' => 'order_test_1',
                'amount' => 10000,
                'currency' => 'INR',
                'status' => 'created',
            ]),
        ]);
        $user = User::factory()->create();
        $user->assignFreePlan();
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();

        $this->actingAs($user)
            ->postJson(route('plans.checkout', $basic))
            ->assertOk()
            ->assertJsonPath('status', 'checkout')
            ->assertJsonPath('order_id', 'order_test_1')
            ->assertJsonPath('key', 'rzp_test_example')
            ->assertJsonPath('amount', 10000)
            ->assertJsonPath('currency', 'INR');

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'plan_id' => $basic->id,
            'amount' => 100,
            'status' => Transaction::PENDING,
            'razorpay_order_id' => 'order_test_1',
        ]);
        $this->assertSame($user->plan_id, $user->fresh()->plan_id);
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
            'status' => Transaction::PENDING,
            'razorpay_order_id' => 'order_test_1',
            'email' => $user->email,
        ]);
        $signature = hash_hmac('sha256', 'order_test_1|pay_test_1', 'test-secret');

        $this->actingAs($user)
            ->postJson(route('plans.verify'), [
                'razorpay_order_id' => 'order_test_1',
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
            'razorpay_order_id' => 'order_test_1',
            'razorpay_payment_id' => 'pay_test_1',
            'status' => Transaction::PAID,
        ]);
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
            'status' => Transaction::PENDING,
            'razorpay_order_id' => 'order_test_1',
            'email' => $user->email,
        ]);

        $this->actingAs($user)
            ->postJson(route('plans.verify'), [
                'razorpay_order_id' => 'order_test_1',
                'razorpay_payment_id' => 'pay_test_1',
                'razorpay_signature' => 'not-the-signature',
            ])
            ->assertUnprocessable();

        $this->assertSame($user->plan_id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('transactions', [
            'razorpay_order_id' => 'order_test_1',
            'status' => Transaction::PENDING,
        ]);
    }

    public function test_the_webhook_marks_a_captured_payment_as_paid(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();
        $pro = Plan::query()->where('code', Plan::PRO)->firstOrFail();
        Transaction::query()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'price_id' => $pro->inrPrice()->id,
            'plan_name' => 'Pro',
            'amount' => 300,
            'currency' => 'INR',
            'status' => Transaction::PENDING,
            'razorpay_order_id' => 'order_hook_1',
            'email' => $user->email,
        ]);
        $body = json_encode([
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_hook_1',
                        'order_id' => 'order_hook_1',
                        'status' => 'captured',
                        'method' => 'upi',
                        'amount' => 30000,
                        'currency' => 'INR',
                        'vpa' => 'advocate@upi',
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'hook-secret');

        $this->call('POST', '/webhooks/razorpay', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
        ], $body)->assertOk()->assertJsonPath('status', 'paid');

        $this->assertSame($pro->id, $user->fresh()->plan_id);
        $this->assertDatabaseHas('transactions', [
            'razorpay_order_id' => 'order_hook_1',
            'razorpay_payment_id' => 'pay_hook_1',
            'method' => 'upi',
            'status' => Transaction::PAID,
        ]);
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
}
