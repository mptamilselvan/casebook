<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseEvent;
use App\Models\CourtCase;
use App\Support\Casebook;
use App\Support\CasebookData;
use App\Support\RoleAccess;
use App\Support\Usage;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseEventController extends Controller
{
    public function store(Request $request, CourtCase $case): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'EVENT', 'create');

        return response()->json($this->save($request, $case, new CaseEvent), 201);
    }

    public function update(Request $request, CourtCase $case, CaseEvent $event): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'EVENT', 'edit');
        abort_unless($event->case_id === $case->id, 404);

        return response()->json($this->save($request, $case, $event));
    }

    public function destroy(Request $request, CourtCase $case, CaseEvent $event): JsonResponse
    {
        RoleAccess::authorize($request->user(), 'EVENT', 'delete');
        abort_unless($event->case_id === $case->id, 404);

        if ($event->file_path) {
            Casebook::files()->delete($event->file_path);
        }

        $event->delete();

        return response()->json(CasebookData::caseDetail($case));
    }

    public function file(Request $request, CaseEvent $event): StreamedResponse
    {
        RoleAccess::authorize($request->user(), 'TIMELINE', 'view');
        abort_unless($event->file_path && Casebook::files()->exists($event->file_path), 404);

        $name = $event->file_name ?: basename($event->file_path);

        return Casebook::files()->response($event->file_path, $name);
    }

    private function save(Request $request, CourtCase $case, CaseEvent $event): array
    {
        $data = $request->validate([
            'event_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => Casebook::FILE_RULE,
        ]);

        $payload = [
            'event_date' => $data['event_date'],
            'title' => $data['title'],
            'description' => $data['description'] ?? '',
            'is_hearing' => $request->boolean('is_hearing'),
        ];

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $incoming = (int) ($file->getSize() ?: 0);
            if ($reason = Usage::storageBlockReason($request->user(), $incoming, (int) ($event->bytes ?? 0))) {
                throw ValidationException::withMessages(['file' => $reason]);
            }

            if ($event->exists && $event->file_path) {
                Casebook::files()->delete($event->file_path);
            }

            $payload['file_path'] = $file->store('event-files', Casebook::FILE_DISK);
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = Casebook::sizeLabel($incoming);
            $payload['bytes'] = $incoming;
        }

        if ($event->exists) {
            $event->update($payload);
        } else {
            $event = $case->events()->create($payload);
        }

        return CasebookData::event($event->fresh());
    }
}
