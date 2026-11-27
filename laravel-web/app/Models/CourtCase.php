<?php

namespace App\Models;

use App\Enums\CaseMode;
use App\Support\Casebook;
use App\Support\Usage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtCase extends Model
{
    protected $table = 'cases';

    protected $fillable = [
        'user_id',
        'title',
        'parties_label',
        'state',
        'district',
        'court',
        'case_number',
        'case_year',
        'type',
        'status',
        'priority',
        'next_hearing',
        'judge',
        'stage',
        'is_pinned',
        'tasks',
        'party1_role',
        'party2_role',
        'mode',
    ];

    protected function casts(): array
    {
        return [
            'next_hearing' => 'date',
            'is_pinned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            $user = auth()->user() ?? request()->user();
            if ($user === null) {
                return;
            }

            if ($user->owner_id) {
                $builder->where($builder->qualifyColumn('user_id'), $user->owner_id)
                    ->whereIn($builder->qualifyColumn('id'), function ($query) use ($user) {
                        $query->select('case_id')
                            ->from('case_user')
                            ->where('user_id', $user->id);
                    });

                return;
            }

            $builder->where($builder->qualifyColumn('user_id'), $user->id);
        });

        static::addGlobalScope('mode', function (Builder $builder) {
            $mode = CaseMode::current();
            if ($mode === null) {
                return;
            }

            $builder->where($builder->qualifyColumn('mode'), $mode->value);
        });

        static::creating(function (CourtCase $case) {
            $user = auth()->user() ?? request()->user();
            if ($user === null) {
                return;
            }

            $case->user_id = $user->owner_id ?: $user->id;
            if (! filled($case->mode)) {
                $case->mode = CaseMode::current()?->value ?? CaseMode::Personal->value;
            }
        });

        static::created(function (CourtCase $case) {
            $user = auth()->user() ?? request()->user();
            if ($user && $user->owner_id && (int) $case->user_id === (int) $user->owner_id) {
                $case->viewers()->syncWithoutDetaching([$user->id]);
            }

            if ($case->user_id) {
                Usage::change((int) $case->user_id, cases: 1);
            }
        });

        static::deleting(function (CourtCase $case) {
            if (! $case->user_id) {
                return;
            }

            $bytes = (int) $case->documents()->withoutGlobalScope('owner')->sum('bytes')
                + (int) $case->events()->withoutGlobalScope('owner')->sum('bytes');
            Usage::change((int) $case->user_id, bytes: -$bytes, cases: -1);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'case_user', 'case_id', 'user_id');
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
        $disk = Casebook::files();

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
