<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_seeder_creates_roles_and_permission_options(): void
    {
        $this->assertSame(array_keys(Role::SLUGS), Role::query()->orderBy('id')->pluck('slug')->all());
        $this->assertSame(28, Permission::query()->count());
        $this->assertEqualsCanonicalizing(
            array_keys(Permission::GROUPS),
            Permission::query()->distinct()->pluck('group')->all(),
        );

        foreach (Permission::ACTIONS as $action) {
            $this->assertSame(7, Permission::query()->where('action', $action)->count());
        }

        $this->assertSame(28, Role::query()->where('slug', 'member')->first()->permissions()->count());
    }

    public function test_superadmin_can_assign_any_user_to_any_role(): void
    {
        $superadmin = User::factory()->create([
            'email' => 'root@casebook.test',
            'role' => User::ROLE_SUPERADMIN,
        ]);
        $advocate = User::factory()->create([
            'email' => 'advocate@casebook.test',
            'role' => User::ROLE_MEMBER,
        ]);

        $this->actingAs($superadmin)
            ->get(route('admin.roles.index'))
            ->assertOk()
            ->assertSee('advocate@casebook.test')
            ->assertSee('Co-admin')
            ->assertDontSee('root@casebook.test')
            ->assertDontSee('value="superadmin"', false);

        $this->actingAs($superadmin)
            ->put(route('admin.roles.update', $advocate), ['role' => User::ROLE_CO_ADMIN])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(User::ROLE_CO_ADMIN, $advocate->fresh()->role);

        $this->actingAs($superadmin)
            ->from(route('admin.roles.index'))
            ->put(route('admin.roles.update', $superadmin), ['role' => User::ROLE_MEMBER])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertTrue($superadmin->fresh()->isSuperAdmin());

        $this->actingAs($superadmin)
            ->from(route('admin.roles.index'))
            ->put(route('admin.roles.update', $advocate), ['role' => User::ROLE_SUPERADMIN])
            ->assertRedirect(route('admin.roles.index'))
            ->assertSessionHasErrors('role');

        $this->assertSame(User::ROLE_CO_ADMIN, $advocate->fresh()->role);
    }

    public function test_a_normal_role_cannot_open_the_admin_role_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.roles.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($admin)
            ->get(route('admin.permissions.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_superadmin_can_save_permission_options(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
        $member = Role::query()->where('slug', User::ROLE_MEMBER)->first();
        $superadminRole = Role::query()->where('slug', User::ROLE_SUPERADMIN)->first();
        $viewCase = Permission::query()->where('group', 'CASE')->where('action', 'view')->first();

        $this->actingAs($superadmin)
            ->get(route('admin.permissions.index'))
            ->assertOk()
            ->assertSee('Pin case')
            ->assertSee('Star case')
            ->assertDontSee('Members')
            ->assertDontSee('Save permissions')
            ->assertDontSee('Co-admin');

        $this->actingAs($superadmin)
            ->put(route('admin.permissions.update'), [
                'role_id' => $member->id,
                'grants' => [$viewCase->id],
            ])
            ->assertRedirect(route('admin.permissions.index', ['role' => User::ROLE_MEMBER]))
            ->assertSessionHas('status', 'Member permissions saved.');

        $this->assertSame([$viewCase->id], $member->permissions()->pluck('permissions.id')->all());
        $this->assertSame(28, Role::query()->where('slug', User::ROLE_ADMIN)->first()->permissions()->count());

        $this->actingAs($superadmin)
            ->put(route('admin.permissions.update'), [
                'role_id' => $superadminRole->id,
                'grants' => [],
            ])
            ->assertRedirect(route('admin.permissions.index', ['role' => User::ROLE_SUPERADMIN]));

        $this->assertSame(28, $superadminRole->permissions()->count());
    }
}
