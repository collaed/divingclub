<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EventRegistration> */
class EventRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => 'confirmed',
        ];
    }

    public function waiting(int $position = 1): static
    {
        return $this->state(['status' => 'waiting', 'waiting_list_position' => $position]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled', 'cancelled_at' => now()]);
    }

    public function nonMember(string $name = 'Guest Diver'): static
    {
        return $this->state(['user_id' => null, 'non_member_name' => $name]);
    }
}
