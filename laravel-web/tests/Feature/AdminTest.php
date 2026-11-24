<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_admin_login_requires_email_and_password_before_authentication(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Admin sign in')
            ->assertSee('Email')
            ->assertSee('Password');

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => 'not-an-email',
                'password' => '',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_only_an_admin_can_open_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $advocate = User::factory()->create([
            'email' => 'advocate@casebook.test',
            'password' => 'password',
            'role' => User::ROLE_USER,
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $advocate->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = User::factory()->create([
            'name' => 'admin',
            'email' => 'admin@casebook.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->post(route('admin.login.store'), [
            'email' => 'Admin@Casebook.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->post(route('admin.login.store'), [
            'email' => 'Admin@Casebook.test',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Hello, admin')
            ->assertSee('Users Management')
            ->assertSee('Users')
            ->assertSee('Cases');
    }

    public function test_admin_cannot_use_the_passwordless_sign_in(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@casebook.test',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $admin->email,
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_admin_seeder_creates_the_admin_account(): void
    {
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', AdminUserSeeder::EMAIL)->first();

        $this->assertNotNull($admin);
        $this->assertSame('admin', $admin->name);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());

        $this->post(route('admin.login.store'), [
            'email' => AdminUserSeeder::EMAIL,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
