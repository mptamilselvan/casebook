<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'plan_id',
        'price_id',
        'plan_name',
        'amount',
        'currency',
        'interval',
        'status',
        'razorpay_order_id',
        'razorpay_subscription_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'method',
        'email',
        'paid_at',
        'gateway',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'gateway' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
