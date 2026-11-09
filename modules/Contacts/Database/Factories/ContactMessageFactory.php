<?php

namespace Modules\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\ContactMessage;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

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
            'phone' => null,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'locale' => (string) config('cms.default_locale'),
            'status' => ContactMessage::NEW,
        ];
    }

    public function handled(): static
    {
        return $this->state(['status' => ContactMessage::HANDLED]);
    }
}
