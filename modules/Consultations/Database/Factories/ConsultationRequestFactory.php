<?php

namespace Modules\Consultations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * @extends Factory<ConsultationRequest>
 */
class ConsultationRequestFactory extends Factory
{
    protected $model = ConsultationRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'service' => fake()->randomElement([null, 'Web Development', 'UI/UX Design']),
            'message' => fake()->sentence(12),
            'locale' => (string) config('cms.default_locale'),
            'status' => ConsultationRequest::NEW,
        ];
    }

    public function handled(): static
    {
        return $this->state(['status' => ConsultationRequest::HANDLED]);
    }
}
