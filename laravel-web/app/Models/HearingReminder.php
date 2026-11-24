<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HearingReminder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'hearing_on',
        'days_before',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'hearing_on' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function courtCase(): BelongsTo
    {
        return $this->belongsTo(CourtCase::class, 'case_id');
    }
}
