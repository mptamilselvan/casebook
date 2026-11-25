<?php

namespace App\Support;

use App\Models\CaseEvent;
use App\Models\CaseParty;
use App\Models\CourtCase;
use App\Models\Document;
use App\Models\HearingReminder;
use App\Models\Template;
use App\Models\User;
use App\Support\Usage;

class CasebookData
{
    public static function user(User $user): array
    {
        $user->loadMissing('plan');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'plan_name' => $user->plan?->name ?? $user->plan_name,
            'usage' => Usage::summary($user),
        ];
    }

    public static function token(User $user): array
    {
        return [
            'status' => 'authenticated',
            'token' => $user->createToken('mobile')->plainTextToken,
            'user' => self::user($user),
        ];
    }

    public static function caseSummary(CourtCase $case): array
    {
        return [
            'id' => $case->id,
            'user_id' => $case->user_id,
            'title' => $case->title,
            'parties_label' => $case->parties_label,
            'state' => $case->state,
            'district' => $case->district,
            'court' => $case->court,
            'case_number' => $case->case_number,
            'case_year' => $case->case_year,
            'type' => $case->type,
            'status' => $case->status,
            'priority' => $case->priority ?: 'medium',
            'next_hearing' => $case->next_hearing?->toDateString(),
            'judge' => $case->judge,
            'stage' => $case->stage,
            'is_pinned' => (bool) $case->is_pinned,
            'party1_role' => $case->party1_role,
            'party2_role' => $case->party2_role,
        ];
    }

    public static function caseDetail(CourtCase $case): array
    {
        $case->load(['caseParties', 'events', 'documents']);

        return [
            ...self::caseSummary($case),
            'tasks' => $case->tasks ?? '',
            'parties' => $case->caseParties->map(fn (CaseParty $party) => [
                'id' => $party->id,
                'side' => $party->side,
                'name' => $party->name,
                'sort_order' => $party->sort_order,
            ])->values(),
            'events' => $case->events->map(fn (CaseEvent $event) => self::event($event))->values(),
            'documents' => $case->documents->map(fn (Document $document) => self::document($document))->values(),
        ];
    }

    public static function event(CaseEvent $event): array
    {
        return [
            'id' => $event->id,
            'case_id' => $event->case_id,
            'event_date' => $event->event_date?->toDateString(),
            'title' => $event->title,
            'description' => $event->description ?? '',
            'is_hearing' => (bool) $event->is_hearing,
            'file_name' => $event->file_name,
            'file_size' => $event->file_size,
            'file_url' => $event->file_path ? 'events/'.$event->id.'/file' : null,
        ];
    }

    public static function document(Document $document): array
    {
        return [
            'id' => $document->id,
            'case_id' => $document->case_id,
            'name' => $document->name,
            'folder' => $document->folder,
            'size' => $document->size,
            'created_at' => $document->created_at?->toDateString(),
            'file_url' => $document->file_path ? 'documents/'.$document->id.'/file' : null,
        ];
    }

    public static function template(Template $template): array
    {
        return [
            'id' => $template->id,
            'title' => $template->title,
            'content' => $template->content ?? '',
            'export_url' => 'templates/'.$template->id.'/export',
        ];
    }

    public static function notifications(): array
    {
        return HearingReminder::query()
            ->whereHas('courtCase')
            ->with('courtCase:id,title')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HearingReminder $reminder) => [
                'id' => $reminder->id,
                'title' => $reminder->title(),
                'date' => $reminder->sent_at?->format('d M Y') ?? '',
            ])
            ->all();
    }
}
