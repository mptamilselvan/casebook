<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourtCase;
use App\Models\Template;
use App\Support\Casebook;
use App\Support\CasebookData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filter = Casebook::filter($request->query('filter'));
        $today = today();

        $cases = CourtCase::query()
            ->when($filter === "Today's Hearings", fn ($query) => $query->whereDate('next_hearing', $today))
            ->when($filter === 'Upcoming', fn ($query) => $query->whereDate('next_hearing', '>', $today)->orderBy('next_hearing'))
            ->when($filter === 'Starred', fn ($query) => $query->where('is_pinned', true)->orderByDesc('updated_at'))
            ->when($filter === 'All Cases', fn ($query) => $query->orderByDesc('is_pinned')->orderByDesc('updated_at'))
            ->get();

        $active = null;
        if ($request->filled('case')) {
            $active = CourtCase::query()->find($request->integer('case'));
        }
        $active ??= CourtCase::query()->orderByDesc('is_pinned')->orderByDesc('updated_at')->first();

        $templates = Template::query()->orderBy('title')->get();

        return response()->json([
            'filter' => $filter,
            'today' => $today->toDateString(),
            'counts' => [
                'All Cases' => CourtCase::query()->count(),
                "Today's Hearings" => CourtCase::query()->whereDate('next_hearing', $today)->count(),
                'Upcoming' => CourtCase::query()->whereDate('next_hearing', '>', $today)->count(),
                'Starred' => CourtCase::query()->where('is_pinned', true)->count(),
            ],
            'filters' => Casebook::FILTERS,
            'tabs' => Casebook::TABS,
            'party1_roles' => Casebook::PARTY_1,
            'party2_roles' => Casebook::PARTY_2,
            'folders' => Casebook::FOLDERS,
            'cases' => $cases->map(fn (CourtCase $case) => CasebookData::caseSummary($case))->values(),
            'case' => $active ? CasebookData::caseDetail($active) : null,
            'templates' => $templates->map(fn (Template $template) => CasebookData::template($template))->values(),
        ]);
    }

    public function show(CourtCase $case): JsonResponse
    {
        return response()->json(CasebookData::caseDetail($case));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'court' => ['required', 'string', 'max:255'],
            'case_number' => ['nullable', 'string', 'max:100'],
            'case_year' => ['nullable', 'string', 'max:10'],
            'party1_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_1))],
            'party2_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_2))],
            'petitioners' => ['nullable', 'array'],
            'petitioners.*' => ['nullable', 'string', 'max:255'],
            'respondents' => ['nullable', 'array'],
            'respondents.*' => ['nullable', 'string', 'max:255'],
        ]);

        $number = trim((string) ($data['case_number'] ?? ''));
        $year = trim((string) ($data['case_year'] ?? ''));
        $title = trim($number.'/'.$year, '/');

        $case = CourtCase::query()->create([
            'user_id' => $request->user()->id,
            'title' => $title !== '' ? $title : 'Untitled Case',
            'state' => $data['state'] ?? null,
            'district' => $data['district'] ?? null,
            'court' => $data['court'],
            'case_number' => $number !== '' ? $number : null,
            'case_year' => $year !== '' ? $year : null,
            'type' => 'New Filing',
            'status' => 'Active',
            'judge' => 'Unassigned',
            'stage' => 'Drafting Phase',
            'is_pinned' => false,
            'tasks' => '',
            'party1_role' => $data['party1_role'],
            'party2_role' => $data['party2_role'],
            'parties_label' => '',
        ]);

        $this->storeParties($case, $data['petitioners'] ?? [], 1);
        $this->storeParties($case, $data['respondents'] ?? [], 2);
        $case->syncPartiesLabel();

        return response()->json(CasebookData::caseDetail($case), 201);
    }

    public function update(Request $request, CourtCase $case): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'court' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $case->update([
            'title' => $data['title'],
            'court' => $data['court'],
            'status' => filled($data['status'] ?? null) ? $data['status'] : $case->status,
        ]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function destroy(CourtCase $case): JsonResponse
    {
        $case->load(['documents', 'events']);
        $case->deleteStoredFiles();
        $case->delete();

        return response()->json(['status' => 'deleted']);
    }

    public function togglePin(CourtCase $case): JsonResponse
    {
        $case->update(['is_pinned' => ! $case->is_pinned]);

        return response()->json(CasebookData::caseDetail($case));
    }

    public function updateHearing(Request $request, CourtCase $case): JsonResponse
    {
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
