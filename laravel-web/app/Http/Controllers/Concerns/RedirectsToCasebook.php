<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CourtCase;
use App\Support\Casebook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait RedirectsToCasebook
{
    protected function redirectToCase(Request $request, ?CourtCase $case = null, array $extra = [], bool $includeCase = true): RedirectResponse
    {
        $tab = Casebook::tab($extra['tab'] ?? $request->input('tab', $request->query('tab')));
        unset($extra['tab']);

        $params = array_merge([
            'filter' => Casebook::filter($request->input('filter', $request->query('filter'))),
            'tab' => $tab,
        ], $extra);

        $caseId = $case?->id;
        if (! $caseId && $includeCase) {
            $caseId = $request->integer('case') ?: null;
        }

        if ($includeCase && $caseId) {
            $params['case'] = $caseId;
        } else {
            unset($params['case']);
        }

        if (! array_key_exists('template', $params) && $request->filled('template')) {
            $params['template'] = $request->input('template');
        }

        return redirect()->route('cases.index', $params);
    }
}
