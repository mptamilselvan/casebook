<?php

namespace App\Http\Controllers\Api;

use App\Enums\CaseMode;
use App\Http\Controllers\Controller;
use App\Models\CourtCase;
use App\Models\Template;
use App\Support\Casebook;
use App\Support\CasebookData;
use App\Support\IndiaLocations;
use App\Support\RoleAccess;
use App\Support\Usage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = Casebook::filter($request->query('filter'));
        $today = today();
        $can = RoleAccess::map($request->user());

        $cases = $can['CASE']['view']
            ? Casebook::applyList(CourtCase::query(), $filter)->get()
            : collect();

        $active = $cases->firstWhere('id', $request->integer('case')) ?? $cases->first();

        $templates = Template::query()->orderBy('title')->get();

        return response()->json([
            'filter' => $filter,
            'today' => $today->toDateString(),
            'counts' => $can['CASE']['view']
                ? Casebook::caseCounts()
                : array_fill_keys(Casebook::FILTERS, 0),
            'permissions' => $can,
            'filters' => Casebook::FILTERS,
            'tabs' => Casebook::TABS,
            'party1_roles' => Casebook::PARTY_1,
            'party2_roles' => Casebook::PARTY_2,
            'folders' => Casebook::FOLDERS,
            'locations' => IndiaLocations::tree(),
            'cases' => $cases->map(fn (CourtCase $case) => CasebookData::caseSummary($case))->values(),
            'case' => $active ? CasebookData::caseDetail($active) : null,
            'templates' => $templates->map(fn (Template $template) => CasebookData::template($template))->values(),
            'usage' => Usage::summary($request->user()),
            'notifications' => CasebookData::notifications(),
        ]);
    }

    public function show(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'view');

        return response()->json(CasebookData::caseDetail($case));
    }

    public function store(Request $request): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'create');

        $data = $request->validate([
            'state' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'court' => ['required', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:100'],
            'case_year' => ['nullable', 'string', 'max:10'],
            'party1_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_1))],
            'party2_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_2))],
            'mode' => ['nullable', Rule::enum(CaseMode::class)],
            'petitioners' => ['nullable', 'array'],
            'petitioners.*' => ['nullable', 'string', 'max:255'],
            'respondents' => ['nullable', 'array'],
            'respondents.*' => ['nullable', 'string', 'max:255'],
        ]);

        if ($reason = Usage::caseBlockReason($request->user())) {
            throw ValidationException::withMessages(['case' => $reason]);
        }

        IndiaLocations::rememberCourt($data['state'], $data['district'], $data['court']);
        $number = trim((string) ($data['case_number'] ?? ''));
        $year = trim((string) ($data['case_year'] ?? ''));
        $title = trim($number.'/'.$year, '/');

        $attributes = [
            'user_id' => $request->user()->id,
            'title' => $title !== '' ? $title : 'Untitled Case',
            'state' => $data['state'] ?? null,
            'district' => $data['district'] ?? null,
            'court' => $data['court'],
            'case_number' => $number !== '' ? $number : null,
            'case_year' => $year !== '' ? $year : null,
            'type' => 'New Filing',
            'status' => 'pending',
            'judge' => 'Unassigned',
            'stage' => 'Drafting Phase',
            'is_pinned' => false,
            'tasks' => '',
            'party1_role' => $data['party1_role'],
            'party2_role' => $data['party2_role'],
            'parties_label' => '',
        ];
        if (filled($data['mode'] ?? null)) {
            $attributes['mode'] = $data['mode'];
        }
        $case = CourtCase::query()->create($attributes);

        $this->storeParties($case, $data['petitioners'] ?? [], 1);
        $this->storeParties($case, $data['respondents'] ?? [], 2);
        $case->syncPartiesLabel();

        return response()->json(CasebookData::caseDetail($case), 201);
    }

    public function update(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'court' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(Casebook::STATUSES))],
            'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'case_number' => ['nullable', 'string', 'max:100'],
            'case_year' => ['nullable', 'string', 'max:10'],
            'party1_role' => ['nullable', 'in:'.implode(',', array_keys(Casebook::PARTY_1))],
            'party2_role' => ['nullable', 'in:'.implode(',', array_keys(Casebook::PARTY_2))],
            'mode' => ['nullable', Rule::enum(CaseMode::class)],
            'petitioners' => ['nullable', 'array'],
            'petitioners.*' => ['nullable', 'string', 'max:255'],
            'respondents' => ['nullable', 'array'],
            'respondents.*' => ['nullable', 'string', 'max:255'],
        ]);

        $number = trim((string) ($request->exists('case_number') ? ($data['case_number'] ?? '') : $case->case_number));
        $year = trim((string) ($request->exists('case_year') ? ($data['case_year'] ?? '') : $case->case_year));
        $title = trim($data['title']);
        if ($request->exists('case_number') || $request->exists('case_year')) {
            $built = trim($number.'/'.$year, '/');
            if ($built !== '') {
                $title = $built;
            }
        }

        $state = $request->exists('state') ? ($data['state'] ?: null) : $case->state;
        $district = $request->exists('district') ? ($data['district'] ?: null) : $case->district;
        if ($state && $district) {
            IndiaLocations::rememberCourt($state, $district, $data['court']);
        }

        $case->update([
            'title' => $title !== '' ? $title : $case->title,
            'court' => $data['court'],
            'status' => filled($data['status'] ?? null) ? $data['status'] : $case->status,
            'state' => $state,
            'district' => $district,
            'case_number' => $number !== '' ? $number : null,
            'case_year' => $year !== '' ? $year : null,
            'party1_role' => $data['party1_role'] ?? $case->party1_role,
            'party2_role' => $data['party2_role'] ?? $case->party2_role,
            'mode' => filled($data['mode'] ?? null) ? $data['mode'] : $case->mode,
        ]);

        if ($request->exists('petitioners') || $request->exists('respondents')) {
            $case->caseParties()->delete();
            $this->storeParties($case, $data['petitioners'] ?? [], 1);
            $this->storeParties($case, $data['respondents'] ?? [], 2);
            $case->syncPartiesLabel();
        }

        $case->refresh();

        return response()->json(CasebookData::caseDetail($case));
    }

    public function destroy(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'delete');

        $case->load(['documents', 'events']);
        $case->deleteStoredFiles();
        $case->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function togglePin(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorizeAny($request->user(), [
            ['STAR_CASE', 'edit'],
            ['PIN_CASE', 'edit'],
        ]);

        $case->update(['is_pinned' => ! $case->is_pinned]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updatePriority(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'PROMOTE_CASE', 'edit');

        $data = $request->validate([
            'priority' => ['required', 'in:low,medium,high'],
        ]);

        $case->update(['priority' => $data['priority']]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updateStatus(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Casebook::STATUSES))],
        ]);

        $case->update(['status' => $data['status']]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updateMode(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'mode' => ['required', Rule::enum(CaseMode::class)],
        ]);

        $case->update(['mode' => $data['mode']]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updateHearing(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'next_hearing' => ['nullable', 'date'],
        ]);

        $case->update([
            'next_hearing' => $data['next_hearing'] ?? null,
        ]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updateTasks(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'tasks' => ['nullable', 'string', 'max:20000'],
        ]);

        $case->update(['tasks' => $data['tasks'] ?? '']);

        return response()->json(CasebookData::caseDetail($case));
    }

    private function storeParties(CourtCase $case, array $names, int $side): void
    {
        $names = array_values($names);
        if ($names === []) {
            $names = [''];
        }

        foreach ($names as $index => $name) {
            $case->caseParties()->create([
                'side' => $side,
                'name' => trim((string) $name),
                'sort_order' => $index,
            ]);
        }
    }
}
