<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vote> */
class VoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'mode' => 'simple',
            'status' => 'open',
            'opens_at' => now()->subDay(),
            'closes_at' => now()->addWeek(),
            'created_by' => User::factory(),
        ];
    }

    public function election(int $positions = 3): static
    {
        return $this->state(['mode' => 'election', 'num_positions' => $positions, 'allow_change' => false]);
    }

    public function closed(): static
    {
        return $this->state(['status' => 'closed', 'closes_at' => now()->subDay()]);
    }
}
