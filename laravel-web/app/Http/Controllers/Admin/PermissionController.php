<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(): View
    {
        return view('admin.permissions.index', [
            'groups' => Permission::GROUPS,
            'actions' => Permission::ACTIONS,
            'permissions' => Permission::query()->orderBy('id')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'grants' => ['array'],
            'grants.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::query()->findOrFail($data['role_id']);

        if ($role->slug === User::ROLE_SUPERADMIN) {
            $role->permissions()->sync(Permission::query()->pluck('id'));

            return redirect()->route('admin.permissions.index', ['role' => $role->slug]);
        }

        $role->permissions()->sync($data['grants'] ?? []);

        return redirect()
            ->route('admin.permissions.index', ['role' => $role->slug])
            ->with('status', $role->name.' permissions saved.');
    }
}
