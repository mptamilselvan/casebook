<?php

namespace App\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleAccess
{
    /** @var array<int, array<string, array<string, bool>>> */
    private static array $cache = [];

    /**
     * @return array<string, array<string, bool>>
     */
    public static function map(?User $user): array
    {
        $blank = [];
        foreach (array_keys(Permission::GROUPS) as $group) {
            foreach (Permission::ACTIONS as $action) {
                $blank[$group][$action] = false;
            }
        }

        if ($user === null) {
            return $blank;
        }

        if (isset(self::$cache[$user->id])) {
            return self::$cache[$user->id];
        }

        if ($user->isSuperAdmin() || $user->owner_id === null) {
            return self::$cache[$user->id] = self::allowAll();
        }

        $owner = TeamDirectory::permissionOwner($user);
        TeamDirectory::ensurePermissionSet($owner);

        $roleId = Role::query()->where('slug', $user->role)->value('id');
        if ($roleId === null) {
            return self::$cache[$user->id] = $blank;
        }

        $granted = DB::table('owner_role_permission')
            ->join('permissions', 'permissions.id', '=', 'owner_role_permission.permission_id')
            ->where('owner_role_permission.owner_id', $owner->id)
            ->where('owner_role_permission.role_id', $roleId)
            ->get(['permissions.group', 'permissions.action']);

        foreach ($granted as $permission) {
            $blank[$permission->group][$permission->action] = true;
        }

        return self::$cache[$user->id] = $blank;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function allows(?User $user, string $group, string $action): bool
    {
        return (bool) (self::map($user)[$group][$action] ?? false);
    }

    public static function authorize(?User $user, string $group, string $action): void
    {
        abort_unless(self::allows($user, $group, $action), 403);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $checks
     */
    public static function authorizeAny(?User $user, array $checks): void
    {
        foreach ($checks as [$group, $action]) {
            if (self::allows($user, $group, $action)) {
                return;
            }
        }

        abort(403);
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private static function allowAll(): array
    {
        $map = [];
        foreach (array_keys(Permission::GROUPS) as $group) {
            foreach (Permission::ACTIONS as $action) {
                $map[$group][$action] = true;
            }
        }

        return $map;
    }
}
