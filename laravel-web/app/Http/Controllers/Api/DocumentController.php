<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CourtCase;
use App\Models\Document;
use App\Support\Casebook;
use App\Support\CasebookData;
use App\Support\RoleAccess;
use App\Support\Usage;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'DOCUMENT', 'create');

        $data = $request->validate([
            'folder' => ['required', 'in:'.implode(',', Casebook::FOLDERS)],
            'name' => ['nullable', 'string', 'max:255'],
            'file' => Casebook::FILE_RULE,
        ]);

        $uploaded = $request->file('file');
        $bytes = (int) ($uploaded?->getSize() ?: 0);
        if ($reason = Usage::storageBlockReason($request->user(), $bytes)) {
            throw ValidationException::withMessages(['file' => $reason]);
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = $uploaded?->getClientOriginalName() ?: 'Untitled.pdf';
        }

        $document = $case->documents()->create([
            'name' => $name,
            'folder' => $data['folder'],
            'size' => $uploaded ? Casebook::sizeLabel($bytes) : null,
            'bytes' => $bytes,
            'file_path' => $uploaded?->store('documents', Casebook::FILE_DISK),
        ]);

        return response()->json(CasebookData::document($document), 201);
    }

    public function file(Request $request, Document $document): StreamedResponse
    {
        RoleAccess::authorize($request->user(), 'DOCUMENT', 'view');
        abort_unless($document->file_path && Casebook::files()->exists($document->file_path), 404);

        return Casebook::files()->response($document->file_path, $document->name);
    }

    public function destroy(Request $request, CourtCase $case, Document $document): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'DOCUMENT', 'delete');
        abort_unless($document->case_id === $case->id, 404);

        if ($document->file_path && Casebook::files()->exists($document->file_path)) {
            Casebook::files()->delete($document->file_path);
        }

        $document->delete();

        return response()->json(['status' => 'deleted']);
    }
}
