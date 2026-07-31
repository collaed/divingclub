<?php

namespace Database\Factories;

use App\Models\Equipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Equipment> */
class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Regulator', 'BCD', 'Tank', 'Wetsuit', 'Fins']).'-'.fake()->numerify('###'),
            'type' => fake()->randomElement(['regulator', 'bcd', 'tank', 'wetsuit', 'fins', 'mask', 'computer']),
            'serial_number' => fake()->unique()->numerify('SN-######'),
            'status' => 'available',
            'condition' => fake()->randomElement(['new', 'good', 'fair']),
            'is_loanable' => true,
            'location' => fake()->randomElement(['warehouse', 'pool', 'boat']),
        ];
    }

    public function tank(): static
    {
        return $this->state(['type' => 'tank', 'name' => 'Tank 12L', 'volume' => 12, 'working_pressure_bar' => 200]);
    }

    public function loaned(): static
    {
        return $this->state(['status' => 'loaned']);
    }
}
