<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'sort_order',
    ];

    public function label(): string
    {
        return $this->code.' - '.$this->name;
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CourtCase::class);
    }
}
