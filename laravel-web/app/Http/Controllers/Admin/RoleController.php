<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) > 255) {
            $search = mb_substr($search, 0, 255);
        }

        $users = User::query()
            ->where('role', '!=', User::ROLE_SUPERADMIN)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('email', 'like', $like);
            })
            ->orderBy('email')
            ->paginate(10)
            ->withQueryString();

        return view('admin.roles.index', [
            'users' => $users,
            'roles' => Role::query()->where('slug', '!=', User::ROLE_SUPERADMIN)->orderBy('id')->get(),
            'search' => $search,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $assignable = array_values(array_diff(array_keys(Role::SLUGS), [User::ROLE_SUPERADMIN]));

        $data = $request->validate([
            'role' => ['required', Rule::in($assignable)],
        ]);

        if ($user->isSuperAdmin()) {
            return back()->withErrors([
                'role' => 'A superadmin role cannot be changed here.',
            ]);
        }

        $user->update(['role' => $data['role']]);

        return back()->with('status', $user->email.' is now '.Role::SLUGS[$data['role']].'.');
    }
}
