<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TripReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripReceipt> */
class TripReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->trip(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'category' => fake()->randomElement(['general', 'transit']),
            'description' => fake()->sentence(),
            'status' => 'pending',
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => 'approved',
            'approved_amount' => $attr['amount'],
            'reviewed_at' => now(),
        ]);
    }

    public function transit(): static
    {
        return $this->state(['category' => 'transit']);
    }

    public function general(): static
    {
        return $this->state(['category' => 'general']);
    }
}
