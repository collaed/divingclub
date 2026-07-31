<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\TripParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TripParticipant> */
class TripParticipantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->trip(),
            'user_id' => User::factory(),
            'driving_percentage' => 0,
            'local_transit_days' => 0,
        ];
    }

    public function driver(int $pct = 50): static
    {
        return $this->state(['driving_percentage' => $pct]);
    }

    public function nonMember(string $name = 'Guest'): static
    {
        return $this->state(['user_id' => null, 'non_member_name' => $name]);
    }
}
