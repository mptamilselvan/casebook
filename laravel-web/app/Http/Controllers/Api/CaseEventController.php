<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaseEvent;
use App\Models\CourtCase;
use App\Support\Casebook;
use App\Support\CasebookData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaseEventController extends Controller
{
    public function store(Request $request, CourtCase $case): JsonResponse
    {
        return response()->json($this->save($request, $case, new CaseEvent), 201);
    }

    public function update(Request $request, CourtCase $case, CaseEvent $event): JsonResponse
    {
        abort_unless($event->case_id === $case->id, 404);

        return response()->json($this->save($request, $case, $event));
    }

    public function destroy(CourtCase $case, CaseEvent $event): JsonResponse
    {
        abort_unless($event->case_id === $case->id, 404);

        if ($event->file_path) {
            Storage::disk('public')->delete($event->file_path);
        }

        $event->delete();

        return response()->json(CasebookData::caseDetail($case));
    }

    public function file(CaseEvent $event): StreamedResponse
    {
        abort_unless($event->file_path && Storage::disk('public')->exists($event->file_path), 404);

        $name = $event->file_name ?: basename($event->file_path);

        return Storage::disk('public')->response($event->file_path, $name);
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
            if ($event->exists && $event->file_path) {
                Storage::disk('public')->delete($event->file_path);
            }

            $file = $request->file('file');
            $payload['file_path'] = $file->store('event-files', 'public');
            $payload['file_name'] = $file->getClientOriginalName();
            $payload['file_size'] = Casebook::sizeLabel($file->getSize() ?: 0);
        }

        if ($event->exists) {
            $event->update($payload);
        } else {
            $event = $case->events()->create($payload);
        }

        return CasebookData::event($event->fresh());
    }
}
