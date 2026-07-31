<?php

namespace Database\Factories;

use App\Models\MemberDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MemberDetail> */
class MemberDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'sex' => fake()->randomElement(['M', 'F']),
            'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
            'phone_mobile' => fake()->phoneNumber(),
            'nationality' => fake()->country(),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->phoneNumber(),
            'emergency_contact_relationship' => fake()->randomElement(['spouse', 'parent', 'sibling']),
        ];
    }

    public function instructor(): static
    {
        return $this->state([
            'active_instructor' => true,
            'bureau_member' => false,
        ]);
    }

    public function bureau(): static
    {
        return $this->state([
            'bureau_member' => true,
            'active_instructor' => false,
        ]);
    }
}
