<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourtCase;
use App\Models\Role;
use App\Models\User;
use App\Support\EmailLogin;
use App\Support\TeamDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'roles' => $this->roles(),
            'cases' => $this->cases(),
            'people' => $request->user()->people()->with('assignedCases')->orderBy('email')->get()->map(fn (User $user) => $this->person($user, ''))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $person = TeamDirectory::savePerson($request->user(), $data['email'], $data['role'], null, $data['case_ids']);

        return response()->json($this->person($person, 'saved'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $this->validated($request);
        $person = TeamDirectory::savePerson($request->user(), $data['email'], $data['role'], $user, $data['case_ids']);

        return response()->json($this->person($person, 'updated'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        TeamDirectory::deletePerson($request->user(), $user);

        return response()->json(['message' => 'Person removed.']);
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
     * @return list<array{id: int, title: string, label: string|null}>
     */
    private function cases(): array
    {
        return CourtCase::query()
            ->orderBy('title')
            ->get(['id', 'title', 'parties_label'])
            ->map(fn (CourtCase $case) => [
                'id' => $case->id,
                'title' => $case->title,
                'label' => $case->parties_label,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    private function roles(): array
    {
        return collect(TeamDirectory::ASSIGNABLE)
            ->map(fn (string $slug) => ['slug' => $slug, 'name' => Role::SLUGS[$slug]])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, email: string, role: string, role_name: string, case_ids: list<int>, message?: string}
     */
    private function person(User $user, string $verb): array
    {
        $caseIds = ($user->relationLoaded('assignedCases')
            ? $user->assignedCases->pluck('id')
            : $user->assignedCases()->pluck('cases.id'))
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $payload = [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'role_name' => $user->roleName(),
            'case_ids' => $caseIds,
        ];

        if ($verb !== '') {
            $payload['message'] = $user->hasVerifiedEmail()
                ? $user->email.' '.$verb.'.'
                : 'We sent a verification link to '.$user->email.'.';
        }

        return $payload;
    }
}
