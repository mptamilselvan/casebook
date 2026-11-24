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

    public function title(): string
    {
        $when = match ((int) $this->days_before) {
            7 => '1 week',
            3 => '3 days',
            2 => '2 days',
            1 => '1 day',
            default => $this->days_before.' days',
        };
        $caseTitle = $this->courtCase?->title;

        return $caseTitle ? 'Hearing in '.$when.' · '.$caseTitle : 'Hearing in '.$when;
    }
}
