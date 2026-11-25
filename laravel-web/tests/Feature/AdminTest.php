<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\SuperAdminUserSeeder;
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
            ->assertSee('Superadmin sign in')
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

    public function test_only_a_superadmin_can_open_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $member = User::factory()->create([
            'email' => 'advocate@casebook.test',
            'password' => 'password',
            'role' => User::ROLE_MEMBER,
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $member->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $admin = User::factory()->create([
            'email' => 'office@casebook.test',
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);

        $this->from(route('admin.login'))
            ->post(route('admin.login.store'), [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $superadmin = User::factory()->create([
            'name' => 'superadmin',
            'email' => 'admin@casebook.test',
            'password' => 'password',
            'role' => User::ROLE_SUPERADMIN,
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

        $this->assertAuthenticatedAs($superadmin);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Hello, superadmin')
            ->assertSee('Users Management')
            ->assertSee('Roles')
            ->assertSee('Permissions')
            ->assertSee('Users')
            ->assertSee('Cases');
    }

    public function test_superadmin_cannot_use_the_passwordless_sign_in(): void
    {
        $superadmin = User::factory()->create([
            'email' => 'admin@casebook.test',
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $superadmin->email,
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->postJson('/api/login', [
            'email' => $superadmin->email,
        ])->assertForbidden()
            ->assertJsonPath('message', 'Superadmin accounts sign in from the admin page.');
    }

    public function test_app_roles_can_use_the_passwordless_sign_in(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_CO_ADMIN, User::ROLE_ELDER, User::ROLE_MEMBER] as $role) {
            $user = User::factory()->create([
                'email' => $role.'@casebook.test',
                'role' => $role,
            ]);

            $this->post(route('login.store'), [
                'email' => $user->email,
            ])->assertRedirect(route('cases.index'));

            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_superadmin_seeder_creates_the_superadmin_account(): void
    {
        $this->seed(SuperAdminUserSeeder::class);

        $admin = User::query()->where('email', SuperAdminUserSeeder::EMAIL)->first();

        $this->assertNotNull($admin);
        $this->assertSame('superadmin', $admin->name);
        $this->assertTrue($admin->isSuperAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());

        $this->post(route('admin.login.store'), [
            'email' => SuperAdminUserSeeder::EMAIL,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));
    }
}
