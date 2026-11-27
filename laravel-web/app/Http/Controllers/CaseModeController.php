<?php

namespace App\Http\Controllers;

use App\Enums\CaseMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CaseModeController extends Controller
{
    public function create(): View
    {
        return view('casebook.mode', [
            'current' => CaseMode::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::enum(CaseMode::class)],
        ]);

        $request->session()->put('case_mode', $data['mode']);

        return redirect()->route('cases.index');
    }
}
