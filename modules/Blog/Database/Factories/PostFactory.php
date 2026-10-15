<?php

namespace Modules\Blog\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Blog\Models\Post;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locale = config('cms.default_locale');

        return [
            'title' => [$locale => rtrim(fake()->unique()->sentence(4), '.')],
            'excerpt' => [$locale => fake()->sentence(12)],
            'body' => [$locale => '<p>'.fake()->paragraph().'</p>'],
            'status' => Post::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => Post::PUBLISHED, 'published_at' => now()->subHour()]);
    }

    public function scheduled(): static
    {
        return $this->state(['status' => Post::PUBLISHED, 'published_at' => now()->addDay()]);
    }
}
