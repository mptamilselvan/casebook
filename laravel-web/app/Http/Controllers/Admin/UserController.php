<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        if (mb_strlen($search) > 255) {
            $search = mb_substr($search, 0, 255);
        }

        $users = User::query()
            ->with(['plan', 'usage'])
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where('email', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'search' => $search,
        ]);
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user())) {
            return back()->withErrors([
                'user' => 'You cannot change your own account.',
            ]);
        }

        $user->update([
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        if (! $user->is_enabled) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('status', $user->is_enabled ? 'User enabled.' : 'User disabled.');
    }
}
