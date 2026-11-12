<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CourtCase;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    use RedirectsToCasebook;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
        ]);

        $case = CourtCase::query()->find($request->integer('case'));

        if ($validator->fails()) {
            return $this->redirectToCase($request, $case, ['tab' => 'Templates'])
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'template');
        }

        $template = Template::query()->create([
            'title' => $validator->validated()['title'],
            'content' => '',
        ]);

        return $this->redirectToCase($request, $case, [
            'tab' => 'Templates',
            'template' => $template->id,
        ])->with('status', 'Template created.');
    }

    public function update(Request $request, Template $template)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:200000'],
        ]);

        $template->update(['content' => $data['content'] ?? '']);
        $case = CourtCase::query()->find($request->integer('case'));

        return $this->redirectToCase($request, $case, [
            'tab' => 'Templates',
            'template' => $template->id,
        ])->with('status', 'Template saved.');
    }

    public function export(Template $template)
    {
        $body = e($template->content ?? '');
        $html = '<html><head><meta charset="utf-8"></head><body><pre style="font-family:Georgia,serif;white-space:pre-wrap;">'.$body.'</pre></body></html>';
        $filename = Str::slug($template->title) ?: 'template';

        return response($html, 200, [
            'Content-Type' => 'application/msword',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.doc"',
        ]);
    }
}
