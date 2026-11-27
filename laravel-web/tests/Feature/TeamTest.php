<?php

namespace Tests\Feature;

use App\Mail\VerifyEmail;
use App\Models\CourtCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleAccess;
use App\Support\TeamDirectory;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_new_registration_is_an_admin(): void
    {
        Mail::fake();

        $this->post(route('login.store'), [
            'email' => 'new.advocate@firm.test',
        ])->assertRedirect();

        $user = User::query()->where('email', 'new.advocate@firm.test')->first();

        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertNull($user->owner_id);
    }

    public function test_verifying_a_member_without_an_owner_makes_them_an_admin(): void
    {
        $user = User::factory()->unverified()->create([
            'role' => User::ROLE_MEMBER,
        ]);

        $this->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]))->assertRedirect(route('cases.index'));

        $this->assertSame(User::ROLE_ADMIN, $user->fresh()->role);
    }

    public function test_an_account_can_add_update_and_remove_people(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $existing = User::factory()->create([
            'email' => 'clerk@firm.test',
            'role' => User::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->post(route('people.store'), [
                'email' => 'clerk@firm.test',
                'role' => User::ROLE_ELDER,
            ])
            ->assertRedirect(route('people.index'));

        $this->assertSame($owner->id, $existing->fresh()->owner_id);
        $this->assertSame(User::ROLE_ELDER, $existing->fresh()->role);

        $this->actingAs($owner)
            ->post(route('people.store'), [
                'email' => 'junior@firm.test',
                'role' => User::ROLE_CO_ADMIN,
            ])
            ->assertRedirect(route('people.index'));

        $junior = User::query()->where('email', 'junior@firm.test')->first();
        $this->assertSame($owner->id, $junior->owner_id);
        $this->assertSame(User::ROLE_CO_ADMIN, $junior->role);
        $this->assertNull($junior->email_verified_at);
        Mail::assertSent(VerifyEmail::class, function (VerifyEmail $mail) use ($junior) {
            return $mail->hasTo('junior@firm.test')
                && str_contains($mail->verificationUrl(), '/email/verify/'.$junior->id.'/');
        });

        $this->actingAs($owner)
            ->get(route('people.index'))
            ->assertOk()
            ->assertSee('clerk@firm.test')
            ->assertSee('junior@firm.test')
            ->assertSee('People Management')
            ->assertSee('Actions')
            ->assertSee('Edit')
            ->assertSee('Delete')
            ->assertSee('Cases they can open')
            ->assertDontSee('value="superadmin"', false);

        $this->actingAs($owner)
            ->put(route('people.update', $junior), [
                'email' => 'junior@firm.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertRedirect(route('people.index'));

        $this->assertSame(User::ROLE_MEMBER, $junior->fresh()->role);

        $this->actingAs($owner)
            ->delete(route('people.destroy', $junior))
            ->assertRedirect(route('people.index'));

        $this->assertNull($junior->fresh());
    }

    public function test_adding_an_unverified_person_sends_a_verification_link(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $pending = User::factory()->unverified()->create([
            'email' => 'pending@firm.test',
            'role' => User::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->postJson('/api/people', [
                'email' => 'pending@firm.test',
                'role' => User::ROLE_ELDER,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'We sent a verification link to pending@firm.test.');

        $this->assertSame($owner->id, $pending->fresh()->owner_id);
        Mail::assertSent(VerifyEmail::class, fn (VerifyEmail $mail) => $mail->hasTo('pending@firm.test'));
    }

    public function test_changing_a_person_email_sends_a_new_verification_link(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $person = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'owner_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->put(route('people.update', $person), [
                'email' => 'renamed@firm.test',
                'role' => User::ROLE_MEMBER,
            ])
            ->assertRedirect(route('people.index'))
            ->assertSessionHas('status', 'We sent a verification link to renamed@firm.test.');

        $this->assertNull($person->fresh()->email_verified_at);
        Mail::assertSent(VerifyEmail::class, fn (VerifyEmail $mail) => $mail->hasTo('renamed@firm.test'));
    }

    public function test_permissions_follow_the_account_owner(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $child = User::factory()->create([
            'role' => User::ROLE_ELDER,
            'owner_id' => $owner->id,
        ]);
        $member = Role::query()->where('slug', User::ROLE_MEMBER)->first();
        $viewCase = Permission::query()->where('group', 'CASE')->where('action', 'view')->first();

        $this->actingAs($owner)
            ->get(route('team.permissions.index', ['role' => User::ROLE_MEMBER]))
            ->assertOk()
            ->assertSee('Save permissions')
            ->assertSee('Member');

        $this->actingAs($owner)
            ->put(route('team.permissions.update'), [
                'role_id' => $member->id,
                'grants' => [$viewCase->id],
            ])
            ->assertRedirect(route('team.permissions.index', ['role' => User::ROLE_MEMBER]));

        $this->assertSame(
            [$viewCase->id],
            \Illuminate\Support\Facades\DB::table('owner_role_permission')
                ->where('owner_id', $owner->id)
                ->where('role_id', $member->id)
                ->pluck('permission_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        );

        $this->actingAs($child)
            ->get(route('team.permissions.index', ['role' => User::ROLE_MEMBER]))
            ->assertOk()
            ->assertDontSee('Save permissions');

        $this->actingAs($child)
            ->put(route('team.permissions.update'), [
                'role_id' => $member->id,
                'grants' => [],
            ])
            ->assertForbidden();

        $this->assertTrue(TeamDirectory::permissionOwner($child)->is($owner));
    }

    public function test_a_person_only_sees_the_cases_checked_for_them(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $shared = CourtCase::query()->create([
            'user_id' => $owner->id,
            'title' => 'Shared hearing',
            'court' => 'City Court',
        ]);
        $private = CourtCase::query()->create([
            'user_id' => $owner->id,
            'title' => 'Private hearing',
            'court' => 'City Court',
        ]);
        $outsider = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $foreign = CourtCase::query()->create([
            'user_id' => $outsider->id,
            'title' => 'Foreign hearing',
            'court' => 'City Court',
        ]);

        $this->actingAs($owner)
            ->post(route('people.store'), [
                'email' => 'junior@firm.test',
                'role' => User::ROLE_MEMBER,
                'case_ids' => [$shared->id, $foreign->id],
            ])
            ->assertRedirect(route('people.index'));

        $junior = User::query()->where('email', 'junior@firm.test')->first();
        $this->assertSame([$shared->id], $junior->assignedCases()->pluck('cases.id')->map(fn ($id) => (int) $id)->all());
        $junior->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($junior)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee('Shared hearing')
            ->assertDontSee('Private hearing')
            ->assertDontSee('Foreign hearing');

        $token = $junior->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonCount(1, 'cases')
            ->assertJsonPath('cases.0.id', $shared->id)
            ->assertJsonPath('case.id', $shared->id);

        $this->withToken($token)
            ->getJson('/api/cases/'.$private->id)
            ->assertNotFound();

        $this->actingAs($owner)
            ->put(route('people.update', $junior), [
                'email' => 'junior@firm.test',
                'role' => User::ROLE_MEMBER,
                'case_ids' => [],
            ])
            ->assertRedirect(route('people.index'));

        $this->actingAs($junior, 'sanctum')
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonCount(0, 'cases');

        $this->actingAs($junior)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertDontSee('Shared hearing');
    }

    public function test_case_actions_follow_the_person_role(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $shared = CourtCase::query()->create([
            'user_id' => $owner->id,
            'title' => 'Shared hearing',
            'court' => 'City Court',
        ]);
        $shared->events()->create([
            'event_date' => '2026-11-27',
            'title' => 'Hidden filing',
        ]);
        $shared->documents()->create([
            'name' => 'Hidden brief',
            'folder' => 'Pleadings',
        ]);
        $junior = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'owner_id' => $owner->id,
        ]);
        $junior->assignedCases()->sync([$shared->id]);

        $viewCase = Permission::query()->where('group', 'CASE')->where('action', 'view')->first();
        $member = Role::query()->where('slug', User::ROLE_MEMBER)->first();
        TeamDirectory::savePermissions($owner, $member->id, [$viewCase->id]);
        RoleAccess::flush();

        $this->actingAs($owner)
            ->get(route('cases.index', ['case' => $shared->id]))
            ->assertOk()
            ->assertSee('New Case')
            ->assertSee('Add Event')
            ->assertSee('Upload Document')
            ->assertSee('Hidden filing')
            ->assertSee('Hidden brief');

        $this->actingAs($junior)
            ->get(route('cases.index', ['case' => $shared->id]))
            ->assertOk()
            ->assertSee('Shared hearing')
            ->assertSee('Parties')
            ->assertDontSee('New Case')
            ->assertDontSee('Add Event')
            ->assertDontSee('Upload Document')
            ->assertDontSee('Edit Case Info')
            ->assertDontSee('Delete case')
            ->assertDontSee('Add Party')
            ->assertDontSee('Hidden filing')
            ->assertDontSee('Hidden brief');

        $this->actingAs($junior)->post(route('cases.store'), [])->assertForbidden();
        $this->actingAs($junior)->delete(route('cases.destroy', $shared))->assertForbidden();
        $this->actingAs($junior)->post(route('cases.pin', $shared))->assertForbidden();
        $this->actingAs($junior)->post(route('cases.hearing', $shared), [])->assertForbidden();
        $this->actingAs($junior)->post(route('events.store', $shared), [])->assertForbidden();
        $this->actingAs($junior)->post(route('documents.store', $shared), [])->assertForbidden();

        $this->actingAs($junior, 'sanctum')
            ->getJson('/api/casebook')
            ->assertOk()
            ->assertJsonCount(1, 'cases')
            ->assertJsonPath('permissions.CASE.view', true)
            ->assertJsonPath('permissions.CASE.create', false)
            ->assertJsonPath('permissions.EVENT.create', false)
            ->assertJsonPath('permissions.DOCUMENT.view', false)
            ->assertJsonPath('permissions.TIMELINE.view', false)
            ->assertJsonPath('case.events', [])
            ->assertJsonPath('case.documents', []);

        $this->actingAs($junior, 'sanctum')->postJson('/api/cases', [])->assertForbidden();
        $this->actingAs($junior, 'sanctum')->deleteJson('/api/cases/'.$shared->id)->assertForbidden();
        $this->actingAs($junior, 'sanctum')->postJson('/api/cases/'.$shared->id.'/pin', [])->assertForbidden();
        $this->actingAs($junior, 'sanctum')->postJson('/api/cases/'.$shared->id.'/events', [])->assertForbidden();
        $this->actingAs($junior, 'sanctum')->postJson('/api/cases/'.$shared->id.'/documents', [])->assertForbidden();

        $this->assertTrue(CourtCase::query()->whereKey($shared->id)->exists());
    }
}
