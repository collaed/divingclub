<?php

namespace Database\Factories;

use App\Models\ThemeSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ThemeSetting> */
class ThemeSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->word(),
            'value' => fake()->word(),
        ];
    }
}
