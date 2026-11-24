<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserUsage;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_can_search_and_page_through_users(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@casebook.test',
            'role' => User::ROLE_ADMIN,
            'created_at' => now(),
        ]);
        $match = User::factory()->create([
            'email' => 'findme@casebook.test',
            'created_at' => now(),
        ]);
        User::factory()->count(9)->create([
            'created_at' => now()->subDay(),
        ]);
        $older = User::factory()->create([
            'email' => 'older.page.two@casebook.test',
            'created_at' => now()->subMonth(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Users Management')
            ->assertSee('Plan')
            ->assertSee('Storage')
            ->assertSee('Cases')
            ->assertSee('Actions')
            ->assertSee($match->email)
            ->assertSee('Disable')
            ->assertDontSee($older->email)
            ->assertSee('Next');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['page' => 2]))
            ->assertOk()
            ->assertSee($older->email)
            ->assertSee('Previous');

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'findme']))
            ->assertOk()
            ->assertSee($match->email)
            ->assertDontSee($older->email)
            ->assertDontSee('Next');
    }

    public function test_user_list_shows_plan_storage_and_case_count(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $advocate = User::factory()->create([
            'email' => 'usage@casebook.test',
        ]);
        $advocate->assignFreePlan();
        UserUsage::query()->create([
            'user_id' => $advocate->id,
            'storage_bytes' => 2048,
            'cases_count' => 41,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'usage@casebook.test']))
            ->assertOk()
            ->assertSeeInOrder(['Free', '2.0 KB', '41']);
    }

    public function test_enable_and_disable_update_the_user(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);
        $advocate = User::factory()->create([
            'email' => 'advocate@casebook.test',
            'is_enabled' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $advocate), ['is_enabled' => '0'])
            ->assertRedirect()
            ->assertSessionHas('status', 'User disabled.');

        $this->assertFalse($advocate->fresh()->is_enabled);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'advocate@casebook.test']))
            ->assertSee('Enable')
            ->assertDontSee('Disable');

        $this->actingAs($admin)
            ->patch(route('admin.users.status', $advocate), ['is_enabled' => '1'])
            ->assertSessionHas('status', 'User enabled.');

        $this->assertTrue($advocate->fresh()->is_enabled);
    }

    public function test_an_admin_cannot_disable_their_own_account(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.status', $admin), ['is_enabled' => '0'])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_enabled);
    }

    public function test_a_disabled_user_cannot_sign_in(): void
    {
        $advocate = User::factory()->create([
            'email' => 'blocked@casebook.test',
            'is_enabled' => false,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), ['email' => $advocate->email])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
