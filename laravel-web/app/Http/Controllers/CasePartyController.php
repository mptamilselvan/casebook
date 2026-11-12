<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CourtCase;
use App\Support\Casebook;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CasePartyController extends Controller
{
    use RedirectsToCasebook;

    public function sync(Request $request, CourtCase $case)
    {
        $data = $request->validate([
            'party1_role' => ['required', Rule::in(array_keys(Casebook::PARTY_1))],
            'party2_role' => ['required', Rule::in(array_keys(Casebook::PARTY_2))],
            'side1' => ['nullable', 'array'],
            'side1.*' => ['nullable', 'string', 'max:255'],
            'side2' => ['nullable', 'array'],
            'side2.*' => ['nullable', 'string', 'max:255'],
            'add_side' => ['nullable', Rule::in(['1', '2'])],
        ]);

        $side1 = array_values($data['side1'] ?? []);
        $side2 = array_values($data['side2'] ?? []);
        if ($side1 === []) {
            $side1 = [''];
        }
        if ($side2 === []) {
            $side2 = [''];
        }
        if (($data['add_side'] ?? null) === '1') {
            $side1[] = '';
        }
        if (($data['add_side'] ?? null) === '2') {
            $side2[] = '';
        }

        $case->update([
            'party1_role' => $data['party1_role'],
            'party2_role' => $data['party2_role'],
        ]);

        $case->caseParties()->delete();

        foreach ($side1 as $index => $name) {
            $case->caseParties()->create([
                'side' => 1,
                'name' => trim((string) $name),
                'sort_order' => $index,
            ]);
        }

        foreach ($side2 as $index => $name) {
            $case->caseParties()->create([
                'side' => 2,
                'name' => trim((string) $name),
                'sort_order' => $index,
            ]);
        }

        $case->syncPartiesLabel();

        return $this->redirectToCase($request, $case);
    }
}
