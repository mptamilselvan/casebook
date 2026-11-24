<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\District;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndiaLocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = json_decode(
            file_get_contents(database_path('seeders/data/india-locations.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        DB::transaction(function () use ($locations) {
            foreach ($locations as $stateData) {
                $state = State::query()->firstOrCreate(['name' => $stateData['name']]);

                foreach ($stateData['courts'] as $name) {
                    Court::query()->firstOrCreate([
                        'state_id' => $state->id,
                        'district_id' => null,
                        'name' => $name,
                    ]);
                }

                foreach ($stateData['districts'] as $districtData) {
                    $district = District::query()->firstOrCreate([
                        'state_id' => $state->id,
                        'name' => $districtData['name'],
                    ]);

                    foreach ($districtData['courts'] as $name) {
                        Court::query()->firstOrCreate([
                            'state_id' => $state->id,
                            'district_id' => $district->id,
                            'name' => $name,
                        ]);
                    }
                }
            }
        });
    }
}
