<?php

namespace App\Http\Controllers;

use App\Support\TeamDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamPermissionController extends Controller
{
    public function index(Request $request): View
    {
        return view('casebook.permissions', TeamDirectory::permissionMatrix(
            $request->user(),
            $request->query('role'),
        ));
    }

    public function update(Request $request): RedirectResponse
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

        return redirect()
            ->route('team.permissions.index', ['role' => $role->slug])
            ->with('status', $role->name.' permissions saved.');
    }
}
