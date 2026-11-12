<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CourtCase;
use App\Models\Template;
use App\Support\Casebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class CaseController extends Controller
{
    use RedirectsToCasebook;

    public function index(Request $request): View
    {
        $filter = Casebook::filter($request->query('filter'));
        $tab = Casebook::tab($request->query('tab'));
        $today = today();

        $cases = CourtCase::query()
            ->when($filter === "Today's Hearings", fn ($query) => $query->whereDate('next_hearing', $today))
            ->when($filter === 'Upcoming', fn ($query) => $query->whereDate('next_hearing', '>', $today)->orderBy('next_hearing'))
            ->when($filter === 'Starred', fn ($query) => $query->where('is_pinned', true)->orderByDesc('updated_at'))
            ->when($filter === 'All Cases', fn ($query) => $query->orderByDesc('is_pinned')->orderByDesc('updated_at'))
            ->get();

        $active = null;
        if ($request->filled('case')) {
            $active = CourtCase::with(['caseParties', 'events', 'documents'])->find($request->integer('case'));
        }
        $active ??= CourtCase::with(['caseParties', 'events', 'documents'])->orderByDesc('is_pinned')->orderByDesc('updated_at')->first();

        $templates = Template::query()->orderBy('title')->get();
        $activeTemplate = Template::query()->find($request->integer('template')) ?? $templates->first();

        return view('casebook.index', [
            'cases' => $cases,
            'active' => $active,
            'filter' => $filter,
            'tab' => $tab,
            'today' => $today->toDateString(),
            'counts' => [
                'All Cases' => CourtCase::query()->count(),
                "Today's Hearings" => CourtCase::query()->whereDate('next_hearing', $today)->count(),
                'Upcoming' => CourtCase::query()->whereDate('next_hearing', '>', $today)->count(),
                'Starred' => CourtCase::query()->where('is_pinned', true)->count(),
            ],
            'templates' => $templates,
            'activeTemplate' => $activeTemplate,
            'party1Roles' => Casebook::PARTY_1,
            'party2Roles' => Casebook::PARTY_2,
            'folders' => Casebook::FOLDERS,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->redirectToCase($request)
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'new-case')
                ->with('modal_step', $validator->errors()->hasAny(['court', 'state', 'district']) ? 1 : 2);
        }

        $data = $validator->validated();
        $number = trim((string) ($data['case_number'] ?? ''));
        $year = trim((string) ($data['case_year'] ?? ''));
        $title = trim($number.'/'.$year, '/');

        $case = CourtCase::query()->create([
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

        return $this->redirectToCase($request, $case, ['tab' => 'Timeline'])
            ->with('status', 'Case file created.');
    }

    public function update(Request $request, CourtCase $case)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'court' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->redirectToCase($request, $case)
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'edit-case')
                ->with('edit_case', [
                    'id' => $case->id,
                    'title' => $request->input('title'),
                    'court' => $request->input('court'),
                ]);
        }

        $case->update($validator->validated());

        return $this->redirectToCase($request, $case)->with('status', 'Case info saved.');
    }

    public function destroy(Request $request, CourtCase $case)
    {
        $deletedId = $case->id;
        $case->load(['documents', 'events']);
        $case->deleteStoredFiles();
        $case->delete();

        $next = CourtCase::query()->find($request->integer('case'));
        if ($next?->id === $deletedId) {
            $next = null;
        }

        return $this->redirectToCase($request, $next, [], $next !== null)->with('status', 'Case deleted.');
    }

    public function togglePin(Request $request, CourtCase $case)
    {
        $case->update(['is_pinned' => ! $case->is_pinned]);
        $active = CourtCase::query()->find($request->integer('case')) ?? $case;

        return $this->redirectToCase($request, $active);
    }

    public function updateHearing(Request $request, CourtCase $case)
    {
        $data = $request->validate([
            'next_hearing' => ['nullable', 'date'],
        ]);

        $case->update([
            'next_hearing' => $data['next_hearing'] ?? null,
        ]);

        return $this->redirectToCase($request, $case);
    }

    public function updateTasks(Request $request, CourtCase $case)
    {
        $data = $request->validate([
            'tasks' => ['nullable', 'string', 'max:20000'],
        ]);

        $case->update(['tasks' => $data['tasks'] ?? '']);

        return $this->redirectToCase($request, $case);
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
