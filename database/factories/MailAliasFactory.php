<?php

namespace Database\Factories;

use App\Models\MailAlias;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MailAlias> */
class MailAliasFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'alias' => fake()->unique()->userName(),
            'type' => 'member',
            'active' => true,
            'hit_count' => 0,
        ];
    }

    public function sasConv(): static
    {
        return $this->state(['type' => 'sas_conv']);
    }
}
