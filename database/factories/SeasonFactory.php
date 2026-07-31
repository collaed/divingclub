<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Season> */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        $year = fake()->numberBetween(2023, 2027);

        return [
            'year' => $year,
            'name' => "Season $year/$year",
            'start_date' => "$year-09-01",
            'end_date' => ($year + 1).'-07-31',
            'is_active' => true,
        ];
    }
}
