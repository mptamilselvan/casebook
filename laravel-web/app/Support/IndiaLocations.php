<?php

namespace App\Support;

use App\Models\District;
use App\Models\State;

class IndiaLocations
{
    /**
     * States, their districts, and the courts that belong to each district.
     * High courts are listed with every district in that state.
     *
     * @return list<array{name: string, districts: list<array{name: string, courts: list<string>}>}>
     */
    public static function tree(): array
    {
        return State::query()
            ->with([
                'districts' => fn ($query) => $query->orderBy('name'),
                'districts.courts' => fn ($query) => $query->orderBy('name'),
                'courts' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (State $state) {
                $highCourts = $state->courts
                    ->whereNull('district_id')
                    ->pluck('name');

                return [
                    'name' => $state->name,
                    'districts' => $state->districts->map(function (District $district) use ($highCourts) {
                        return [
                            'name' => $district->name,
                            'courts' => $highCourts
                                ->merge($district->courts->pluck('name'))
                                ->unique()
                                ->values()
                                ->all(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
