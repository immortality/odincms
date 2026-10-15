<?php

namespace App\Cms\Support;

use Closure;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Field definitions shared by Settings sections and widget settings:
 *
 *   'limit' => ['type' => 'number', 'label' => 'blog::widgets.limit', 'default' => 5, 'rules' => ['integer', 'min:1']],
 *   'body'  => ['type' => 'translatable', 'input' => 'editor', 'label' => 'Text'],
 *
 * Types: text, email, number, textarea, editor (TinyMCE), select, multiselect (with "options": an array
 * or a closure), checkbox, translatable (one value per locale; "input" = input|textarea|editor; "max" = the
 * length limit of each language; "required" => true = the default language must be filled in).
 * Rendered by <x-admin.form.field>. HTML from editors is cleaned on save with HtmlSanitizer ("purify" => false turns it off).
 */
class FormFields
{
    public const TYPES = ['text', 'email', 'number', 'textarea', 'editor', 'select', 'multiselect', 'checkbox', 'translatable'];

    /**
     * Check the definitions and fill in the type.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(array $fields, string $owner): array
    {
        foreach ($fields as $name => $field) {
            $type = $field['type'] ?? 'text';
            if (! in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException("Field \"{$owner}.{$name}\" has an unknown type \"{$type}\".");
            }
            $fields[$name]['type'] = $type;
        }

        return $fields;
    }

    /**
     * Validation rules from each field's type plus its own "rules".
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function rules(array $fields): array
    {
        $rules = [];

        foreach ($fields as $name => $field) {
            $own = (array) ($field['rules'] ?? []);

            switch ($field['type']) {
                case 'email':
                    $rules[$name] = ['nullable', 'string', 'email', 'max:255', ...$own];
                    break;
                case 'number':
                    $rules[$name] = ['nullable', 'numeric', ...$own];
                    break;
                case 'checkbox':
                    $rules[$name] = ['boolean'];
                    break;
                case 'select':
                    $rules[$name] = ['nullable', Rule::in(array_map('strval', array_keys(self::options($field)))), ...$own];
                    break;
                case 'multiselect':
                    $rules[$name] = ['array', ...$own];
                    $rules[$name.'.*'] = [Rule::in(array_map('strval', array_keys(self::options($field))))];
                    break;
                case 'translatable':
                    $max = 'max:'.($field['max'] ?? (($field['input'] ?? 'input') === 'input' ? 1000 : 100000));
                    $rules[$name] = ['nullable', 'array', ...$own];
                    $rules[$name.'.*'] = ['nullable', 'string', $max];
                    if ($field['required'] ?? false) {
                        $rules[$name.'.'.config('cms.default_locale')] = ['required', 'string', $max];
                    }
                    break;
                case 'textarea':
                case 'editor':
                    $rules[$name] = ['nullable', 'string', 'max:100000', ...$own];
                    break;
                default: // text
                    $rules[$name] = ['nullable', 'string', 'max:255', ...$own];
            }
        }

        return $rules;
    }

    /**
     * Validation attribute names: each field's label, and "Label (EN)" for each language of a translatable field.
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    public static function attributes(array $fields, string $prefix = ''): array
    {
        $attributes = [];

        foreach ($fields as $name => $field) {
            $label = (string) __((string) ($field['label'] ?? $name));
            $attributes[$prefix.$name] = $label;

            if ($field['type'] === 'translatable') {
                foreach ((array) config('cms.locales') as $locale) {
                    $attributes[$prefix.$name.'.'.$locale] = $label.' ('.strtoupper((string) $locale).')';
                }
            }
        }

        return $attributes;
    }

    /**
     * Turn validated input into the values to store (checkbox → bool, number → int/float, editor HTML purified, ...).
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> name => value
     */
    public static function values(array $fields, array $input): array
    {
        $values = [];

        foreach ($fields as $name => $field) {
            $value = $input[$name] ?? null;

            $value = match ($field['type']) {
                'checkbox' => (bool) $value,
                'number' => $value === null || $value === '' ? null : $value + 0,
                'multiselect' => array_values((array) $value),
                'translatable' => array_filter((array) $value, fn ($text) => filled($text)),
                default => $value,
            };

            $values[$name] = self::shouldPurify($field) && $value !== null ? HtmlSanitizer::clean($value) : $value;
        }

        return $values;
    }

    /**
     * The defaults of the fields (closures resolved).
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    public static function defaults(array $fields): array
    {
        $defaults = [];
        foreach ($fields as $name => $field) {
            $defaults[$name] = self::resolve($field['default'] ?? null);
        }

        return $defaults;
    }

    /**
     * A select field's options (an array, or a closure returning one).
     *
     * @param  array<string, mixed>  $field
     * @return array<string|int, string>
     */
    public static function options(array $field): array
    {
        return (array) self::resolve($field['options'] ?? []);
    }

    public static function resolve(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function shouldPurify(array $field): bool
    {
        $html = $field['type'] === 'editor' || ($field['type'] === 'translatable' && ($field['input'] ?? null) === 'editor');

        return (bool) ($field['purify'] ?? $html);
    }
}
