<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Role::SLUGS as $slug => $name) {
            Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        foreach (array_keys(Permission::GROUPS) as $group) {
            foreach (Permission::ACTIONS as $action) {
                Permission::query()->updateOrCreate(
                    ['group' => $group, 'action' => $action],
                );
            }
        }

        $permissionIds = Permission::query()->pluck('id');

        Role::query()->each(function (Role $role) use ($permissionIds) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        });
    }
}
