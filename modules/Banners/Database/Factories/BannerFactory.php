<?php

namespace Modules\Banners\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Banners\Models\Banner;

/**
 * @extends Factory<Banner>
 */
class BannerFactory extends Factory
{
    protected $model = Banner::class;

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
            'highlight' => [$locale => fake()->words(2, true)],
            'text' => [$locale => fake()->sentence(12)],
            'button_label' => [$locale => 'Get started'],
            'button_url' => '#contact',
            'position' => 0,
            'status' => Banner::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => Banner::PUBLISHED]);
    }
}
