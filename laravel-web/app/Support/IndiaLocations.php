<?php

namespace App\Support;

use App\Models\Court;
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

    public static function rememberCourt(string $stateName, string $districtName, string $courtName): void
    {
        $courtName = trim($courtName);
        if ($courtName === '') {
            return;
        }

        $state = State::query()->where('name', $stateName)->first();
        $district = $state?->districts()->where('name', $districtName)->first();
        if ($state === null || $district === null) {
            return;
        }

        $known = Court::query()
            ->where('state_id', $state->id)
            ->where(function ($query) use ($district) {
                $query->whereNull('district_id')->orWhere('district_id', $district->id);
            })
            ->pluck('name');

        $needle = mb_strtolower($courtName);
        if ($known->contains(fn (string $name) => mb_strtolower($name) === $needle)) {
            return;
        }

        Court::query()->create([
            'state_id' => $state->id,
            'district_id' => $district->id,
            'name' => $courtName,
        ]);
    }
}
