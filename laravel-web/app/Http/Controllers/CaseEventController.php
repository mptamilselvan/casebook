<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RedirectsToCasebook;
use App\Models\CaseEvent;
use App\Models\CourtCase;
use App\Support\Casebook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CaseEventController extends Controller
{
    use RedirectsToCasebook;

    public function store(Request $request, CourtCase $case)
    {
        return $this->save($request, $case, new CaseEvent);
    }

    public function update(Request $request, CourtCase $case, CaseEvent $event)
    {
        abort_unless($event->case_id === $case->id, 404);

        return $this->save($request, $case, $event);
    }

    public function destroy(Request $request, CourtCase $case, CaseEvent $event)
    {
        abort_unless($event->case_id === $case->id, 404);

        if ($event->file_path) {
            Storage::disk('public')->delete($event->file_path);
        }

        $event->delete();

        return $this->redirectToCase($request, $case)->with('status', 'Event deleted.');
    }

    public function file(Request $request, CaseEvent $event)
    {
        abort_unless($event->file_path && Storage::disk('public')->exists($event->file_path), 404);

        $name = $event->file_name ?: basename($event->file_path);

        return $request->boolean('download')
            ? Storage::disk('public')->download($event->file_path, $name)
            : Storage::disk('public')->response($event->file_path, $name);
    }

    private function save(Request $request, CourtCase $case, CaseEvent $event)
    {
        $validator = Validator::make($request->all(), [
            'event_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'file' => Casebook::FILE_RULE,
        ]);

        if ($validator->fails()) {
            return $this->redirectToCase($request, $case)
                ->withErrors($validator)
                ->withInput()
                ->with('modal', 'event');
        }

        $data = $validator->validated();
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
            $case->events()->create($payload);
        }

        return $this->redirectToCase($request, $case, ['tab' => 'Timeline'])->with('status', 'Event saved.');
    }
}
