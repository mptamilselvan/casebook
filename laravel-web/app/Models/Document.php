<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUsersCase;
use App\Support\Usage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use BelongsToUsersCase;

    protected $fillable = [
        'name',
        'folder',
        'size',
        'bytes',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Document $document) {
            self::recordBytes($document, (int) $document->bytes);
        });

        static::deleted(function (Document $document) {
            self::recordBytes($document, -1 * (int) $document->bytes);
        });
    }

    private static function recordBytes(Document $document, int $bytes): void
    {
        if ($bytes === 0) {
            return;
        }

        $userId = Usage::userIdForCase((int) $document->case_id);
        if ($userId !== null) {
            Usage::change($userId, bytes: $bytes);
        }
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'case_id');
    }
}
