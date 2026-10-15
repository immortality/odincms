<?php

namespace Modules\Pages\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Pages\Models\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locale = config('cms.default_locale');

        return [
            'title' => [$locale => rtrim(fake()->unique()->sentence(3), '.')],
            'body' => [$locale => '<p>'.fake()->paragraph().'</p>'],
            'layout' => Page::DEFAULT_LAYOUT,
            'status' => Page::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => Page::PUBLISHED]);
    }

    public function childOf(Page $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
