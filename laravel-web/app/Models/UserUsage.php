<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUsage extends Model
{
    protected $fillable = [
        'user_id',
        'storage_bytes',
        'cases_count',
    ];

    protected function casts(): array
    {
        return [
            'storage_bytes' => 'integer',
            'cases_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
