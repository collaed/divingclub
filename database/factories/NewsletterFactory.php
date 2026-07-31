<?php

namespace Database\Factories;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Newsletter> */
class NewsletterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Newsletter '.fake()->monthName(),
            'month' => now()->format('Y-m'),
            'background_image' => 'default-bulles',
            'slots' => [],
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }
}
