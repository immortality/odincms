<?php

namespace Modules\Contacts\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Contacts\Models\Contact;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locale = config('cms.default_locale');

        return [
            'title' => [$locale => rtrim(fake()->unique()->sentence(2), '.')],
            'address' => [$locale => fake()->streetAddress()],
            'city' => [$locale => fake()->city()],
            'phone' => fake()->numerify('+996 ### ### ###'),
            'email' => fake()->safeEmail(),
            'status' => Contact::DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => Contact::PUBLISHED]);
    }

    public function located(float $latitude = 42.8746, float $longitude = 74.5698): static
    {
        return $this->state(['latitude' => $latitude, 'longitude' => $longitude]);
    }
}
