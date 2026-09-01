<?php

namespace Database\Factories;

use App\Models\MailConversation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MailConversation> */
class MailConversationFactory extends Factory
{
    public function definition(): array
    {
        $token = strtolower(Str::random(10));

        return [
            'external_email' => fake()->unique()->safeEmail(),
            'external_name' => fake()->name(),
            'token' => $token,
            'sas_alias' => "cep+conv.{$token}@clubcep.eu",
            'subject' => fake()->sentence(4),
            'hit_count' => 1,
            'last_activity_at' => now(),
        ];
    }
}
