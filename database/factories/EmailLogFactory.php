<?php

namespace Database\Factories;

use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailLog> */
class EmailLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'to_email' => fake()->safeEmail(),
            'from_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(),
            'body' => fake()->paragraph(),
            'status' => 'sent',
            'direction' => 'outbound',
        ];
    }

    public function inbound(): static
    {
        return $this->state(['direction' => 'inbound', 'status' => 'forwarded']);
    }

    public function pendingReview(): static
    {
        return $this->state(['status' => 'pending_review', 'direction' => 'inbound']);
    }
}
