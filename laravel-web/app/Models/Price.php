<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    public const INR = 'INR';

    protected $fillable = [
        'plan_id',
        'amount',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function label(): string
    {
        return '₹'.number_format($this->amount);
    }
}
