<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsersCase;
use App\Support\Usage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEvent extends Model
{
    use BelongsToUsersCase;

    protected $fillable = [
        'event_date',
        'title',
        'description',
        'file_name',
        'file_size',
        'bytes',
        'file_path',
        'is_hearing',
    ];

    protected static function booted(): void
    {
        static::created(function (CaseEvent $event) {
            self::recordBytes($event, (int) $event->bytes);
        });

        static::updated(function (CaseEvent $event) {
            if (! $event->wasChanged('bytes')) {
                return;
            }

            self::recordBytes($event, (int) $event->bytes - (int) $event->getOriginal('bytes'));
        });

        static::deleted(function (CaseEvent $event) {
            self::recordBytes($event, -1 * (int) $event->bytes);
        });
    }

    private static function recordBytes(CaseEvent $event, int $bytes): void
    {
        if ($bytes === 0) {
            return;
        }

        $userId = Usage::userIdForCase((int) $event->case_id);
        if ($userId !== null) {
            Usage::change($userId, bytes: $bytes);
        }
    }

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_hearing' => 'boolean',
            'bytes' => 'integer',
        ];
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'case_id');
    }
}
