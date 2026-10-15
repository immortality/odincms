<?php

namespace Database\Factories;

use App\Cms\Modules\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state: discovered, not installed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::studly(fake()->unique()->word());

        return [
            'slug' => Str::kebab($name),
            'name' => $name,
            'version' => '1.0.0',
            'installed_version' => null,
            'is_core' => false,
            'installed_at' => null,
            'enabled' => false,
            'is_missing' => false,
            'sort_order' => 0,
        ];
    }

    /**
     * Installed (and enabled) at the manifest version.
     */
    public function installed(bool $enabled = true): static
    {
        return $this->state(fn (array $attributes) => [
            'installed_version' => $attributes['version'],
            'installed_at' => now(),
            'enabled' => $enabled,
        ]);
    }

    /**
     * Installed but disabled.
     */
    public function disabled(): static
    {
        return $this->installed(false);
    }

    public function core(): static
    {
        return $this->state(['is_core' => true]);
    }

    public function missing(): static
    {
        return $this->state(['is_missing' => true]);
    }
}
