<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CourtCase;
use App\Models\Document;
use App\Support\Casebook;
use App\Support\Usage;
use Illuminate\Http\Request;
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
        $bytes = (int) ($uploaded?->getSize() ?: 0);
        if ($reason = Usage::storageBlockReason($request->user(), $bytes)) {
            return $this->redirectToCase($request, $case)
                ->withErrors(['file' => $reason])
                ->withInput()
                ->with('modal', 'document');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $uploaded?->getClientOriginalName() ?: 'Untitled.pdf';
        }

        $case->documents()->create([
            'name' => $name,
            'folder' => $data['folder'],
            'size' => $uploaded ? Casebook::sizeLabel($bytes) : null,
            'bytes' => $bytes,
            'file_path' => $uploaded?->store('documents', Casebook::FILE_DISK),
        ]);

        return $this->redirectToCase($request, $case, ['tab' => 'Documents'])->with('status', 'Document saved.');
    }

    public function file(Request $request, Document $document)
    {
        abort_unless($document->file_path && Casebook::files()->exists($document->file_path), 404);

        return $request->boolean('download')
            ? Casebook::files()->download($document->file_path, $document->name)
            : Casebook::files()->response($document->file_path, $document->name);
    }
}
