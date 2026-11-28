<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CaseType;
use App\Models\CourtCase;
use App\Models\Template;
use App\Models\User;
use App\Support\Casebook;
use App\Support\CasebookData;
use App\Support\IndiaLocations;
use App\Support\RoleAccess;
use App\Support\TeamDirectory;
use App\Support\Usage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaseController extends Controller
{
    use RedirectsToCasebook;

    public function index(Request $request): View
    {
        $filter = Casebook::filter($request->query('filter'));
        $tab = Casebook::tab($request->query('tab'));
        $today = today();
        $can = RoleAccess::map($request->user());
        if (! $can['TIMELINE']['view'] && $tab === 'Timeline') {
            $tab = $can['DOCUMENT']['view'] ? 'Documents' : 'Parties';
        }
        if (! $can['DOCUMENT']['view'] && $tab === 'Documents') {
            $tab = 'Parties';
        }

        $cases = $can['CASE']['view']
            ? Casebook::applyList(CourtCase::query(), $filter)->get()
            : collect();

        $active = $request->filled('case')
            ? $cases->firstWhere('id', $request->integer('case'))
            : null;
        $active?->load([
            'caseParties',
            'events',
            'documents',
            'viewers' => fn ($query) => $query->orderBy('email'),
            'caseType',
        ]);
        $canAssignPeople = $active !== null && (int) $request->user()->id === (int) $active->user_id;
        $team = $canAssignPeople
            ? User::query()->where('owner_id', $active->user_id)->orderBy('email')->get()
            : collect();

        $templates = Template::query()->orderBy('title')->get();
        $activeTemplate = Template::query()->find($request->integer('template')) ?? $templates->first();

        return view('casebook.index', [
            'cases' => $cases,
            'active' => $active,
            'filter' => $filter,
            'tab' => $tab,
            'today' => $today->toDateString(),
            'counts' => $can['CASE']['view']
                ? Casebook::caseCounts()
                : array_fill_keys(Casebook::FILTERS, 0),
            'templates' => $templates,
            'activeTemplate' => $activeTemplate,
            'party1Roles' => Casebook::PARTY_1,
            'party2Roles' => Casebook::PARTY_2,
            'folders' => Casebook::FOLDERS,
            'locations' => IndiaLocations::tree(),
            'notifications' => CasebookData::notifications(),
            'team' => $team,
            'canAssignPeople' => $canAssignPeople,
            'caseTypes' => CaseType::query()->orderBy('sort_order')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'create');

        $validator = Validator::make($request->all(), [
            'state' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'court' => ['required', 'string', 'max:255'],
            'case_type_id' => ['nullable', 'integer', 'exists:case_types,id'],
            'court_type' => ['nullable', 'in:'.implode(',', array_keys(Casebook::COURT_TYPES))],
            'case_number' => ['nullable', 'string', 'max:100'],
            'case_year' => ['nullable', 'string', 'max:10'],
            'next_hearing' => ['nullable', 'date'],
            'party1_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_1))],
            'party2_role' => ['required', 'in:'.implode(',', array_keys(Casebook::PARTY_2))],
            'mode' => ['nullable', Rule::enum(CaseMode::class)],
            'petitioners' => ['nullable', 'array'],
            'petitioners.*' => ['nullable', 'string', 'max:255'],
            'respondents' => ['nullable', 'array'],
            'respondents.*' => ['nullable', 'string', 'max:255'],
            'counsel' => ['nullable', 'array'],
            'counsel.*' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->redirectToCase($request)
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'new-case')
                ->with('modal_step', $validator->errors()->hasAny(['court', 'state', 'district', 'case_type_id', 'court_type']) ? 1 : 2);
        }

        if ($reason = Usage::caseBlockReason($request->user())) {
            return $this->redirectToCase($request)
                ->withErrors(['case' => $reason])
                ->withInput()
                ->with('modal', 'new-case')
                ->with('modal_step', 2);
        }

        $data = $validator->validated();
        IndiaLocations::rememberCourt($data['state'], $data['district'], $data['court'], $data['area'] ?? null);
        $number = trim((string) ($data['case_number'] ?? ''));
        $year = trim((string) ($data['case_year'] ?? ''));
        $title = trim($number.'/'.$year, '/');

        $attributes = [
            'user_id' => $request->user()->id,
            'title' => $title !== '' ? $title : 'Untitled Case',
            'state' => $data['state'] ?? null,
            'district' => $data['district'] ?? null,
            'area' => filled($data['area'] ?? null) ? $data['area'] : null,
            'court' => $data['court'],
            'case_type_id' => $data['case_type_id'] ?? null,
            'court_type' => $data['court_type'] ?? null,
            'case_number' => $number !== '' ? $number : null,
            'case_year' => $year !== '' ? $year : null,
            'next_hearing' => $data['next_hearing'] ?? null,
            'type' => 'New Filing',
            'status' => 'pending',
            'judge' => 'Unassigned',
            'stage' => 'Drafting Phase',
            'is_pinned' => false,
            'tasks' => '',
            'party1_role' => $data['party1_role'],
            'party2_role' => $data['party2_role'],
            'parties_label' => '',
            'counsel' => Casebook::counselList($data['counsel'] ?? [], array_merge($data['petitioners'] ?? [], $data['respondents'] ?? [])),
        ];
        if (filled($data['mode'] ?? null)) {
            $attributes['mode'] = $data['mode'];
        }
        $case = CourtCase::query()->create($attributes);

        $this->storeParties($case, $data['petitioners'] ?? [], 1);
        $this->storeParties($case, $data['respondents'] ?? [], 2);
        $case->syncPartiesLabel();

        $mode = CaseMode::tryFrom((string) $case->mode);
        if ($mode !== null && $request->hasSession() && CaseMode::current() !== $mode) {
            $request->session()->put('case_mode', $mode->value);
        }

        return $this->redirectToCase($request, $case, [
            'tab' => 'Timeline',
            'filter' => 'All',
        ])->with('status', 'Case file created.');
    }

    public function update(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'court' => ['required', 'string', 'max:255'],
            'mode' => ['nullable', Rule::enum(CaseMode::class)],
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
                    'mode' => $request->input('mode', $case->mode),
                ]);
        }

        $attributes = $validator->validated();
        if (! filled($attributes['mode'] ?? null)) {
            unset($attributes['mode']);
        }
        $case->update($attributes);
        $case->refresh();
        $visible = CaseMode::current() === null || $case->mode === CaseMode::current()->value;

        return $this->redirectToCase($request, $visible ? $case : null, [], $visible)
            ->with('status', 'Case info saved.');
    }

    public function destroy(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'delete');

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
        RoleAccess::authorizeAny($request->user(), [
            ['STAR_CASE', 'edit'],
            ['PIN_CASE', 'edit'],
        ]);

        $case->update(['is_pinned' => ! $case->is_pinned]);
        $active = CourtCase::query()->find($request->integer('case')) ?? $case;

        return $this->redirectToCase($request, $active);
    }

    public function updatePriority(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'PROMOTE_CASE', 'edit');

        $data = $request->validate([
            'priority' => ['required', 'in:low,medium,high'],
        ]);

        $case->update(['priority' => $data['priority']]);

        return $this->redirectToCase($request, $case);
    }

    public function updateStatus(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Casebook::STATUSES))],
        ]);

        $case->update(['status' => $data['status']]);

        return $this->redirectToCase($request, $case);
    }

    public function updateMode(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'mode' => ['required', Rule::enum(CaseMode::class)],
        ]);

        $case->update(['mode' => $data['mode']]);
        $case->refresh();
        $visible = CaseMode::current() === null || $case->mode === CaseMode::current()->value;

        return $this->redirectToCase($request, $visible ? $case : null, [], $visible);
    }

    public function updateHearing(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

        $data = $request->validate([
            'next_hearing' => ['nullable', 'date'],
        ]);

        $case->update([
            'next_hearing' => $data['next_hearing'] ?? null,
        ]);

        return $this->redirectToCase($request, $case);
    }

    public function updatePeople(Request $request, CourtCase $case)
    {
        $data = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
        ]);

        TeamDirectory::syncCasePeople(
            $request->user(),
            $case,
            collect($data['user_ids'] ?? [])->map(fn ($id) => (int) $id)->all(),
        );

        return $this->redirectToCase($request, $case);
    }

    public function updateTasks(Request $request, CourtCase $case)
    {
        RoleAccess::authorize($request->user(), 'CASE', 'edit');

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
