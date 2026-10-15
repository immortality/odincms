<?php

namespace App\Cms\Settings;

use App\Cms\Settings\Events\SettingsSaved;
use App\Cms\Support\FormFields;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Site settings stored in the `settings` table (spec §8, Settings), cached as one array.
 *
 *   Settings::get('site.name');               // a registered field's default when not saved yet
 *   Settings::set('blog.per_page', 20);
 *   Settings::section('blog', 'blog::settings.title', [
 *       'per_page' => ['type' => 'number', 'label' => 'blog::settings.per_page', 'default' => 10, 'rules' => ['integer', 'min:1']],
 *   ], module: 'blog');
 *
 * Fields are defined as in App\Cms\Support\FormFields. Uses the query builder only: it is read while
 * the app boots.
 */
class Settings
{
    public const CACHE_KEY = 'cms.settings';

    /**
     * @var array<string, array{key: string, label: string, icon: string, order: int, module: string|null, fields: array<string, array<string, mixed>>}>
     */
    private array $sections = [];

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function __construct(private Repository $cache) {}

    /*
    |--------------------------------------------------------------------------
    | Values
    |--------------------------------------------------------------------------
    */

    /**
     * The saved value, else the registered field's default, else $default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        $field = $this->field($key);

        return $field !== null && array_key_exists('default', $field) ? $this->resolve($field['default']) : $default;
    }

    /**
     * A translatable setting in $locale (default: the current one), falling back to the default locale.
     */
    public function translated(string $key, ?string $locale = null, string $default = ''): string
    {
        $value = $this->get($key);

        if (! is_array($value)) {
            return is_string($value) && $value !== '' ? $value : $default;
        }

        $locale ??= app()->getLocale();
        $translation = $value[$locale] ?? $value[(string) config('cms.default_locale')] ?? Arr::first($value);

        return is_string($translation) && $translation !== '' ? $translation : $default;
    }

    public function set(string $key, mixed $value): void
    {
        [$group] = explode('.', $key, 2);
        $now = Carbon::now();

        DB::table('settings')->upsert(
            [[
                'key' => $key,
                'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'group' => $group,
                'module' => $this->sections[$group]['module'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['key'],
            ['value', 'updated_at'],
        );

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values  key => value
     */
    public function setMany(array $values): void
    {
        $changes = [];
        foreach ($values as $key => $value) {
            $old = $this->get($key);
            if ($old !== $value) {
                $changes[$key] = ['old' => $old, 'new' => $value];
            }
        }

        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        });

        if ($changes !== []) {
            SettingsSaved::dispatch($changes);
        }
    }

    public function forget(string $key): void
    {
        DB::table('settings')->where('key', $key)->delete();
        $this->flush();
    }

    /**
     * Every saved value (key => value). Empty before the `settings` table exists.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        /** @var array<string, mixed>|null $values */
        $values = $this->cache->get(self::CACHE_KEY);

        if ($values === null) {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            $values = DB::table('settings')->pluck('value', 'key')
                ->map(fn ($json) => is_string($json) ? json_decode($json, true) : null)
                ->all();

            $this->cache->forever(self::CACHE_KEY, $values);
        }

        return $this->values = $values;
    }

    public function flush(): void
    {
        $this->values = null;
        $this->cache->forget(self::CACHE_KEY);
    }

    /*
    |--------------------------------------------------------------------------
    | Sections (the Settings screen)
    |--------------------------------------------------------------------------
    */

    /**
     * Register a section of the Settings screen. Labels are translation keys or plain text.
     *
     * @param  array<string, array<string, mixed>>  $fields  name => ['type', 'label', 'default', 'rules', 'options', 'help']
     */
    public function section(string $key, string $label, array $fields, ?string $module = null, string $icon = 'fas fa-sliders-h', int $order = 50): void
    {
        $fields = FormFields::normalize($fields, $key);

        $this->sections[$key] = compact('key', 'label', 'icon', 'order', 'module', 'fields');
    }

    /**
     * @return array<string, array{key: string, label: string, icon: string, order: int, module: string|null, fields: array<string, array<string, mixed>>}>
     */
    public function sections(): array
    {
        $sections = $this->sections;
        uasort($sections, fn (array $a, array $b) => [$a['order'], $a['key']] <=> [$b['order'], $b['key']]);

        return $sections;
    }

    /**
     * @return array{key: string, label: string, icon: string, order: int, module: string|null, fields: array<string, array<string, mixed>>}|null
     */
    public function getSection(string $key): ?array
    {
        return $this->sections[$key] ?? null;
    }

    /**
     * The registered field for "{section}.{name}".
     *
     * @return array<string, mixed>|null
     */
    public function field(string $key): ?array
    {
        [$section, $name] = array_pad(explode('.', $key, 2), 2, '');

        return $this->sections[$section]['fields'][$name] ?? null;
    }

    /**
     * Validation rules for a section's form (see FormFields).
     *
     * @return array<string, mixed>
     */
    public function rules(string $section): array
    {
        return FormFields::rules($this->getSection($section)['fields'] ?? []);
    }

    /**
     * Turn validated form input into the values to store (see FormFields).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> "{section}.{name}" => value
     */
    public function valuesFromInput(string $section, array $input): array
    {
        $values = [];
        foreach (FormFields::values($this->getSection($section)['fields'] ?? [], $input) as $name => $value) {
            $values["{$section}.{$name}"] = $value;
        }

        return $values;
    }

    /**
     * A select field's options.
     *
     * @param  array<string, mixed>  $field
     * @return array<string|int, string>
     */
    public function options(array $field): array
    {
        return FormFields::options($field);
    }

    private function resolve(mixed $value): mixed
    {
        return FormFields::resolve($value);
    }
}
