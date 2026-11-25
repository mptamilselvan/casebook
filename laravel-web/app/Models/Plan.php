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

    public function inrPrice(?string $interval = null): ?Price
    {
        $interval ??= Price::MONTHLY;
        $match = $this->prices->first(
            fn (Price $price) => $price->currency === Price::INR && $price->interval === $interval,
        );

        if ($match !== null || $interval !== Price::MONTHLY) {
            return $match;
        }

        return $this->prices->firstWhere('currency', Price::INR);
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        $prices = collect(Price::INTERVALS)
            ->map(fn (string $interval) => $this->inrPrice($interval))
            ->filter()
            ->map(fn (Price $price) => [
                'id' => $price->id,
                'interval' => $price->interval,
                'amount' => $price->amount,
                'currency' => $price->currency,
                'label' => $price->label(),
            ])
            ->values();
        $monthly = $prices->firstWhere('interval', Price::MONTHLY);

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
            'price' => $monthly,
            'prices' => $prices,
        ];
    }
}
