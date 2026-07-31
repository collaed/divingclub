<?php

namespace Database\Factories;

use App\Models\DiveSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DiveSite> */
class DiveSiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city().' '.fake()->randomElement(['Quarry', 'Lake', 'Reef', 'Wreck']),
            'country' => fake()->country(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'max_depth' => fake()->numberBetween(10, 60),
            'water_type' => fake()->randomElement(['freshwater', 'saltwater']),
            'is_active' => true,
        ];
    }
}
