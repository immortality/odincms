<?php

namespace Modules\Categories\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Categories\Models\Category;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'blog',
            'title' => [config('cms.default_locale') => fake()->unique()->words(2, true)],
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function childOf(Category $parent): static
    {
        return $this->state(['type' => $parent->type, 'parent_id' => $parent->id]);
    }
}
