<?php

namespace Database\Factories;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 week', '+3 months');

        return [
            'title' => fake()->sentence(3),
            'event_type' => fake()->randomElement(['pool', 'dive', 'training', 'theory', 'social']),
            'event_date' => $date,
            'event_time' => '19:00',
            'location' => fake()->city(),
            'max_participants' => fake()->randomElement([null, 10, 15, 20, 25]),
            'status' => 'scheduled',
        ];
    }

    public function pool(): static
    {
        return $this->state(['event_type' => 'pool', 'title' => 'Pool Training', 'location' => 'Piscine de Merl']);
    }

    public function trip(): static
    {
        return $this->state([
            'event_type' => 'dive',
            'trip_settlement_enabled' => true,
            'settlement_status' => 'open',
            'end_date' => fn (array $attr) => Carbon::parse($attr['event_date'])->addDays(4),
        ]);
    }

    public function withDeposit(float $amount = 100.0): static
    {
        return $this->state([
            'deposit_1_amount' => $amount,
            'deposit_1_date' => fn (array $attr) => Carbon::parse($attr['event_date'])->subDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }
}
