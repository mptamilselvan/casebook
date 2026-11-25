<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    public function test_plans_and_prices_are_seeded_in_inr(): void
    {
        $this->assertDatabaseHas('plans', ['code' => 'free', 'space_limit' => 1, 'cases_limit' => 10]);
        $this->assertDatabaseHas('plans', ['code' => 'basic', 'space_limit' => 5, 'cases_limit' => 50]);
        $this->assertDatabaseHas('plans', ['code' => 'pro', 'space_limit' => 10, 'cases_limit' => null]);
        $this->assertDatabaseHas('prices', ['amount' => 0, 'currency' => 'INR', 'interval' => 'monthly']);
        $this->assertDatabaseHas('prices', ['amount' => 100, 'currency' => 'INR', 'interval' => 'monthly']);
        $this->assertDatabaseHas('prices', ['amount' => 1000, 'currency' => 'INR', 'interval' => 'yearly']);
        $this->assertDatabaseHas('prices', ['amount' => 300, 'currency' => 'INR', 'interval' => 'monthly']);
        $this->assertDatabaseHas('prices', ['amount' => 3000, 'currency' => 'INR', 'interval' => 'yearly']);
    }

    public function test_verifying_an_email_assigns_the_free_plan(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('cases.index'));

        $user->refresh();
        $this->assertSame('free', $user->plan->code);
        $this->assertSame('Free', $user->plan_name);
        $this->assertSame(0, $user->price->amount);
        $this->assertSame('INR', $user->price->currency);
        $this->assertNotNull($user->plan_created_date);

        $created = $user->plan_created_date->toDateString();
        $this->get($url)->assertRedirect(route('cases.index'));
        $this->assertSame($created, $user->fresh()->plan_created_date->toDateString());
    }

    public function test_prices_page_lists_the_plans(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();

        $this->actingAs($user)
            ->get(route('plans.index'))
            ->assertOk()
            ->assertSee('Prices')
            ->assertSee('Free')
            ->assertSee('Basic')
            ->assertSee('Pro')
            ->assertSee('₹0')
            ->assertSee('₹100')
            ->assertSee('₹1,000')
            ->assertSee('₹300')
            ->assertSee('₹3,000')
            ->assertSee('Monthly')
            ->assertSee('Yearly')
            ->assertSee('per month')
            ->assertSee('Subscribe · ₹100 / month')
            ->assertSee('1 GB')
            ->assertSee('5 GB')
            ->assertSee('10 GB')
            ->assertSee('Your plan');
    }

    public function test_api_lists_plans(): void
    {
        $user = User::factory()->create();
        $user->assignFreePlan();

        $this->withToken($user->createToken('mobile')->plainTextToken)
            ->getJson('/api/plans')
            ->assertOk()
            ->assertJsonPath('currency', 'INR')
            ->assertJsonPath('current_plan_id', $user->plan_id)
            ->assertJsonPath('plans.0.code', 'free')
            ->assertJsonPath('plans.0.price.amount', 0)
            ->assertJsonPath('plans.0.space_label', '1 GB')
            ->assertJsonPath('plans.1.price.amount', 100)
            ->assertJsonPath('plans.1.prices.0.interval', 'monthly')
            ->assertJsonPath('plans.1.prices.1.interval', 'yearly')
            ->assertJsonPath('plans.1.prices.1.amount', 1000)
            ->assertJsonPath('plans.1.space_label', '5 GB')
            ->assertJsonPath('plans.2.code', 'pro')
            ->assertJsonPath('plans.2.price.amount', 300)
            ->assertJsonPath('plans.2.prices.1.amount', 3000)
            ->assertJsonPath('plans.2.space_label', '10 GB')
            ->assertJsonPath('plans.2.cases_limit', null);
    }

    public function test_home_shows_the_signed_in_email_and_plan(): void
    {
        $user = User::factory()->create([
            'email' => 'advocate@firm.test',
        ]);
        $user->assignFreePlan();

        $this->actingAs($user)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee('advocate@firm.test')
            ->assertSee('Free');
    }

    public function test_admin_can_change_plan_limits_and_price(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'password',
        ]);
        $basic = Plan::query()->where('code', Plan::BASIC)->firstOrFail();
        $member = User::factory()->create();
        $member->forceFill([
            'plan_id' => $basic->id,
            'price_id' => $basic->inrPrice()->id,
            'plan_name' => 'Basic',
            'plan_created_date' => now()->toDateString(),
        ])->save();

        $this->actingAs($admin)
            ->get(route('admin.plans.index'))
            ->assertOk()
            ->assertSee('Plans & prices')
            ->assertSee('Storage space (GB)')
            ->assertSee('Cases limit')
            ->assertSee('Monthly price (INR)')
            ->assertSee('Yearly price (INR)');

        $basic->inrPrice('monthly')->update(['razorpay_plan_id' => 'plan_old']);

        $this->put(route('admin.plans.update', $basic), [
            'name' => 'Basic Plus',
            'space_limit' => 8,
            'cases_limit' => 40,
            'monthly_amount' => 150,
            'yearly_amount' => 1500,
        ])->assertRedirect();

        $basic->refresh()->load('prices');
        $this->assertSame('Basic Plus', $basic->name);
        $this->assertSame(8, $basic->space_limit);
        $this->assertSame(40, $basic->cases_limit);
        $this->assertSame(150, $basic->inrPrice('monthly')->amount);
        $this->assertSame(1500, $basic->inrPrice('yearly')->amount);
        $this->assertNull($basic->inrPrice('monthly')->razorpay_plan_id);
        $this->assertSame('INR', $basic->inrPrice()->currency);
        $this->assertSame('Basic Plus', $member->fresh()->plan_name);

        $pro = Plan::query()->where('code', Plan::PRO)->firstOrFail();
        $this->put(route('admin.plans.update', $pro), [
            'name' => 'Pro',
            'space_limit' => 12,
            'cases_limit' => '',
            'monthly_amount' => 300,
            'yearly_amount' => 3000,
        ])->assertRedirect();

        $this->assertNull($pro->fresh()->cases_limit);
        $this->assertSame(12, $pro->fresh()->space_limit);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.plans.index'))
            ->assertRedirect(route('admin.login'));
    }
}
