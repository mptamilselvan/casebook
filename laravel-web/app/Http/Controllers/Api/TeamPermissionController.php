<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\TeamDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamPermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $matrix = TeamDirectory::permissionMatrix($request->user(), $request->query('role'));

        return response()->json([
            'can_edit' => $matrix['can_edit'],
            'actions' => $matrix['actions'],
            'groups' => collect($matrix['groups'])->map(function (string $label, string $group) use ($matrix) {
                $permissions = [];
                foreach ($matrix['actions'] as $action) {
                    $permission = ($matrix['permissions'][$group] ?? collect())->firstWhere('action', $action);
                    if ($permission) {
                        $permissions[$action] = $permission->id;
                    }
                }

                return [
                    'key' => $group,
                    'label' => $label,
                    'permissions' => $permissions,
                ];
            })->values(),
            'roles' => $matrix['roles']->map(fn (Role $role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'grants' => $role->grant_ids,
            ])->values(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'grants' => ['array'],
            'grants.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = TeamDirectory::savePermissions(
            $request->user(),
            (int) $data['role_id'],
            $data['grants'] ?? [],
        );

        return response()->json([
            'role' => $role->slug,
            'message' => $role->name.' permissions saved.',
        ]);
    }
}
