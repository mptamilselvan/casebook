<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseEvent extends Model
{
    protected $fillable = [
        'event_date',
        'title',
        'description',
        'file_name',
        'file_size',
        'file_path',
        'is_hearing',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'is_hearing' => 'boolean',
        ];
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'case_id');
    }
}
