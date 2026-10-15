<?php

namespace Modules\Services\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Services\Models\Service;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

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
            'detail' => [$locale => '<p>'.fake()->paragraph().'</p>'],
            'status' => Service::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => Service::PUBLISHED]);
    }
}
