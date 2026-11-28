<?php

namespace App\Support;

use App\Models\CourtCase;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamDirectory
{
    public const ASSIGNABLE = [
        User::ROLE_ADMIN,
        User::ROLE_CO_ADMIN,
        User::ROLE_ELDER,
        User::ROLE_MEMBER,
    ];

    public static function permissionOwner(User $user): User
    {
        if ($user->owner_id) {
            $owner = User::query()->find($user->owner_id);
            if ($owner) {
                return $owner;
            }
        }

        return $user;
    }

    public static function canEditPermissions(User $user): bool
    {
        return $user->owner_id === null;
    }

    public static function savePerson(User $actor, string $email, string $role, ?User $person = null, ?array $caseIds = null): User
    {
        $email = Str::lower($email);

        if ($person) {
            if ((int) $person->owner_id !== $actor->id) {
                abort(404);
            }

            if (User::query()->where('email', $email)->whereKeyNot($person->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'That email is already used.',
                ]);
            }

            $emailChanged = Str::lower((string) $person->email) !== $email;
            $person->update([
                'email' => $email,
                'role' => $role,
                'owner_id' => $actor->id,
            ]);

            if ($emailChanged) {
                $person->forceFill(['email_verified_at' => null])->save();
            }

            self::syncCases($person, $caseIds);
            self::sendVerification($person, ! $person->hasVerifiedEmail());

            return $person;
        }

        $found = User::query()->where('email', $email)->first();
        if ($found) {
            if ($found->is($actor) || $found->isSuperAdmin() || $found->id === $actor->owner_id) {
                throw ValidationException::withMessages([
                    'email' => 'That account cannot be added.',
                ]);
            }

            $found->update([
                'role' => $role,
                'owner_id' => $actor->id,
            ]);
            self::syncCases($found, $caseIds);
            self::sendVerification($found, ! $found->hasVerifiedEmail());

            return $found;
        }

        $created = User::query()->create([
            'name' => EmailLogin::nameFromEmail($email),
            'email' => $email,
            'password' => Str::password(40),
            'role' => $role,
            'owner_id' => $actor->id,
        ]);
        self::syncCases($created, $caseIds);
        self::sendVerification($created, true);

        return $created;
    }

    /**
     * @param  list<int>|null  $caseIds
     */
    private static function syncCases(User $person, ?array $caseIds): User
    {
        if ($caseIds === null) {
            return $person;
        }

        $allowed = CourtCase::query()
            ->whereIn('id', $caseIds)
            ->pluck('id')
            ->all();
        $person->assignedCases()->sync($allowed);

        return $person;
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function syncCasePeople(User $actor, CourtCase $case, array $userIds): void
    {
        if ((int) $actor->id !== (int) $case->user_id) {
            abort(403);
        }

        $allowed = User::query()
            ->where('owner_id', $case->user_id)
            ->whereIn('id', $userIds)
            ->pluck('id')
            ->all();

        $case->viewers()->sync($allowed);
    }

    private static function sendVerification(User $user, bool $send): void
    {
        if (! $send) {
            return;
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'email' => 'The account was saved, but the verification email could not be sent. Save this email again to retry.',
            ]);
        }
    }

    public static function deletePerson(User $actor, User $person): void
    {
        if ((int) $person->owner_id !== $actor->id) {
            abort(404);
        }

        $person->tokens()->delete();
        $person->delete();
    }

    /**
     * @return array{owner: User, can_edit: bool, roles: \Illuminate\Support\Collection, groups: array<string, string>, actions: list<string>, permissions: \Illuminate\Support\Collection}
     */
    public static function permissionMatrix(User $actor, ?string $roleSlug = null): array
    {
        $owner = self::permissionOwner($actor);
        self::ensurePermissionSet($owner);

        $roles = Role::query()
            ->whereIn('slug', self::ASSIGNABLE)
            ->orderBy('id')
            ->get();
        $selected = $roles->firstWhere('slug', $roleSlug) ?? $roles->first();
        $grants = DB::table('owner_role_permission')
            ->where('owner_id', $owner->id)
            ->get(['role_id', 'permission_id'])
            ->groupBy('role_id');

        $roles->each(function (Role $role) use ($grants) {
            $role->setAttribute(
                'grant_ids',
                ($grants[$role->id] ?? collect())->pluck('permission_id')->map(fn ($id) => (int) $id)->all(),
            );
        });

        return [
            'owner' => $owner,
            'can_edit' => self::canEditPermissions($actor),
            'roles' => $roles,
            'selected' => $selected,
            'groups' => Permission::GROUPS,
            'actions' => Permission::ACTIONS,
            'permissions' => Permission::query()->orderBy('id')->get()->groupBy('group'),
        ];
    }

    public static function savePermissions(User $actor, int $roleId, array $permissionIds): Role
    {
        $owner = self::permissionOwner($actor);
        if ($actor->id !== $owner->id) {
            abort(403);
        }

        self::ensurePermissionSet($owner);

        $role = Role::query()->findOrFail($roleId);
        if (! in_array($role->slug, self::ASSIGNABLE, true)) {
            abort(404);
        }

        $ids = Permission::query()->whereIn('id', $permissionIds)->pluck('id');

        DB::table('owner_role_permission')
            ->where('owner_id', $owner->id)
            ->where('role_id', $role->id)
            ->delete();

        $rows = $ids->map(fn ($id) => [
            'owner_id' => $owner->id,
            'role_id' => $role->id,
            'permission_id' => $id,
        ])->all();

        if ($rows !== []) {
            DB::table('owner_role_permission')->insert($rows);
        }

        return $role;
    }

    public static function ensurePermissionSet(User $owner): void
    {
        $ready = DB::table('owner_permission_sets')->where('owner_id', $owner->id)->exists();
        if ($ready) {
            return;
        }

        $roles = Role::query()->whereIn('slug', self::ASSIGNABLE)->with('permissions')->get();
        $rows = [];
        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $rows[] = [
                    'owner_id' => $owner->id,
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ];
            }
        }

        DB::transaction(function () use ($owner, $rows) {
            if (DB::table('owner_permission_sets')->where('owner_id', $owner->id)->exists()) {
                return;
            }

            DB::table('owner_permission_sets')->insert([
                'owner_id' => $owner->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('owner_role_permission')->insert($chunk);
            }
        });
    }
}
