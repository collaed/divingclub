<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Article> */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'body' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'article_type' => fake()->randomElement(['news', 'training', 'trip_report', 'safety', 'gear']),
            'is_published' => true,
            'is_public' => false,
            'author_id' => User::factory(),
        ];
    }

    public function public(): static
    {
        return $this->state(['is_public' => true]);
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
