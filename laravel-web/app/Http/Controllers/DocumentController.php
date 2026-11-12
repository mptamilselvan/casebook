<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CourtCase;
use App\Models\Document;
use App\Support\Casebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    use RedirectsToCasebook;

    public function store(Request $request, CourtCase $case)
    {
        $validator = Validator::make($request->all(), [
            'folder' => ['required', 'in:'.implode(',', Casebook::FOLDERS)],
            'name' => ['nullable', 'string', 'max:255'],
            'file' => Casebook::FILE_RULE,
        ]);

        if ($validator->fails()) {
            return $this->redirectToCase($request, $case)
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'document');
        }

        $data = $validator->validated();
        $uploaded = $request->file('file');
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $uploaded?->getClientOriginalName() ?: 'Untitled.pdf';
        }

        $case->documents()->create([
            'name' => $name,
            'folder' => $data['folder'],
            'size' => $uploaded ? Casebook::sizeLabel($uploaded->getSize() ?: 0) : null,
            'file_path' => $uploaded?->store('documents', 'public'),
        ]);

        return $this->redirectToCase($request, $case, ['tab' => 'Documents'])->with('status', 'Document saved.');
    }

    public function file(Request $request, Document $document)
    {
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return $request->boolean('download')
            ? Storage::disk('public')->download($document->file_path, $document->name)
            : Storage::disk('public')->response($document->file_path, $document->name);
    }
}
