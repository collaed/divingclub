<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\PageVisit> */
class PageVisitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'method' => 'GET',
            'path' => '/'.fake()->randomElement(['dashboard', 'events', 'members/directory', 'profile', 'library']),
            'route_name' => fake()->randomElement(['dashboard', 'events.index', 'members.directory', null]),
            'status' => 200,
            'ip_address' => fake()->ipv4(),
            'created_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ];
    }
}
