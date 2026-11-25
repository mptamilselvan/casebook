<?php

namespace App\Http\Controllers;

use App\Models\CourtCase;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailLogin;
use App\Support\TeamDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function index(Request $request): View
    {
        return view('casebook.people', [
            'people' => $request->user()->people()->with('assignedCases')->orderBy('email')->get(),
            'cases' => CourtCase::query()->orderBy('title')->get(),
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $person = TeamDirectory::savePerson($request->user(), $data['email'], $data['role'], null, $data['case_ids']);

        return redirect()
            ->route('people.index')
            ->with('status', $this->status($person, 'saved'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request);
        $person = TeamDirectory::savePerson($request->user(), $data['email'], $data['role'], $user, $data['case_ids']);

        return redirect()
            ->route('people.index')
            ->with('status', $this->status($person, 'updated'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        TeamDirectory::deletePerson($request->user(), $user);

        return redirect()
            ->route('people.index')
            ->with('status', 'Person removed.');
    }

    private function status(User $person, string $verb): string
    {
        if ($person->hasVerifiedEmail()) {
            return $person->email.' '.$verb.'.';
        }

        return 'We sent a verification link to '.$person->email.'.';
    }

    /**
     * @return array{email: string, role: string, case_ids: list<int>|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'email' => EmailLogin::RULES,
            'role' => ['required', Rule::in(TeamDirectory::ASSIGNABLE)],
            'case_ids' => ['sometimes', 'array'],
            'case_ids.*' => ['nullable', 'integer'],
        ], EmailLogin::MESSAGES);

        $data['case_ids'] = $request->exists('case_ids')
            ? collect($request->input('case_ids', []))
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all()
            : null;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    private function roles(): array
    {
        return collect(TeamDirectory::ASSIGNABLE)
            ->mapWithKeys(fn (string $slug) => [$slug => Role::SLUGS[$slug]])
            ->all();
    }
}
