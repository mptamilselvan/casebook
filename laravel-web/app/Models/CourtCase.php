<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CourtCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'title',
        'parties_label',
        'state',
        'district',
        'court',
        'case_number',
        'case_year',
        'type',
        'status',
        'next_hearing',
        'judge',
        'stage',
        'is_pinned',
        'tasks',
        'party1_role',
        'party2_role',
    ];

    protected function casts(): array
    {
        return [
            'next_hearing' => 'date',
            'is_pinned' => 'boolean',
        ];
    }

    public function caseParties(): HasMany
    {
        return $this->hasMany(CaseParty::class, 'case_id')->orderBy('side')->orderBy('sort_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class, 'case_id')->orderByDesc('event_date')->orderByDesc('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'case_id')->latest();
    }

    public function syncPartiesLabel(): void
    {
        $this->load('caseParties');

        $left = $this->caseParties->where('side', 1)->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->implode(', ');
        $right = $this->caseParties->where('side', 2)->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->implode(', ');

        $this->parties_label = ($left !== '' ? $left : $this->party1_role)
            .' vs. '
            .($right !== '' ? $right : $this->party2_role);
        $this->save();
    }

    public function deleteStoredFiles(): void
    {
        $disk = Storage::disk('public');

        foreach ($this->documents as $document) {
            if ($document->file_path) {
                $disk->delete($document->file_path);
            }
        }

        foreach ($this->events as $event) {
            if ($event->file_path) {
                $disk->delete($event->file_path);
            }
        }
    }
}
