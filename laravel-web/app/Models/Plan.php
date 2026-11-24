<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const FREE = 'free';

    public const BASIC = 'basic';

    public const PRO = 'pro';

    protected $fillable = [
        'code',
        'name',
        'space_limit',
        'cases_limit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'space_limit' => 'integer',
            'cases_limit' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }

    public function inrPrice(): ?Price
    {
        return $this->prices->firstWhere('currency', Price::INR);
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $price = $this->inrPrice();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'space_limit' => $this->space_limit,
            'cases_limit' => $this->cases_limit,
            'cases_label' => $this->cases_limit === null
                ? 'Unlimited cases'
                : $this->cases_limit.' cases',
            'space_label' => $this->space_limit.' GB',
            'price' => $price === null ? null : [
                'id' => $price->id,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'label' => $price->label(),
            ],
        ];
    }
}
