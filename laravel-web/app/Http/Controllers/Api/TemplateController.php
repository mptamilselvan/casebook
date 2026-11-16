<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\CasebookData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = Template::query()->orderBy('title')->get();

        return response()->json(
            $templates->map(fn (Template $template) => CasebookData::template($template))->values()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $template = Template::query()->create([
            'title' => $data['title'],
            'content' => '',
        ]);

        return response()->json(CasebookData::template($template), 201);
    }

    public function update(Request $request, Template $template): JsonResponse
    {
        $data = $request->validate([
            'content' => ['nullable', 'string', 'max:200000'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $template->update([
            'content' => $data['content'] ?? $template->content,
            'title' => $data['title'] ?? $template->title,
        ]);

        return response()->json(CasebookData::template($template));
    }

    public function export(Template $template): Response
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
