<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Price extends Model
{
    public const INR = 'INR';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const INTERVALS = [
        self::MONTHLY,
        self::YEARLY,
    ];

    protected $fillable = [
        'plan_id',
        'amount',
        'currency',
        'interval',
        'razorpay_plan_id',
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

    public function period(): string
    {
        return $this->interval === self::YEARLY ? 'year' : 'month';
    }
}
