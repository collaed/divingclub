<?php

namespace Database\Factories;

use App\Models\PaymentExpected;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentExpected> */
class PaymentExpectedFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'dues',
            'season_year' => (string) now()->year,
            'amount_due' => fake()->randomFloat(2, 50, 500),
            'communication' => 'CLUB-'.now()->year.'-'.fake()->numerify('###').'-'.strtoupper(fake()->lastName()),
            'status' => 'pending',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attr) => [
            'status' => 'paid',
            'amount_paid' => $attr['amount_due'],
            'paid_at' => now(),
        ]);
    }

    public function event(int $eventId): static
    {
        return $this->state(['type' => 'event', 'event_id' => $eventId]);
    }
}
