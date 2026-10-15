<?php

namespace Modules\Contacts\Http\Requests;

use App\Cms\Media\CroppedImageInput;
use App\Cms\Support\Slug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Contacts\Models\Contact;

/**
 * Create and update a contact. Authorized by ContactPolicy before validation, so users without
 * permission get 403 instead of validation errors. An empty slug is made from the title; the
 * coordinates are both given or both left empty.
 */
class ContactRequest extends FormRequest
{
    /** Translatable fields => max length. */
    private const TRANSLATABLE = ['title' => 255, 'address' => 255, 'city' => 120];

    public function authorize(): bool
    {
        $contact = $this->contact();

        return $contact !== null
            ? (bool) $this->user()?->can('update', $contact)
            : (bool) $this->user()?->can('create', Contact::class);
    }

    public function contact(): ?Contact
    {
        $contact = $this->route('contact');

        return $contact instanceof Contact ? $contact : null;
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('slug'))) {
            $this->merge(['slug' => Slug::make((string) $this->input('slug'))]);
        }

        // "42,87" typed with a decimal comma is fine too.
        foreach (['latitude', 'longitude'] as $field) {
            if (is_string($value = $this->input($field))) {
                $this->merge([$field => str_replace(',', '.', trim($value))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $default = config('cms.default_locale');

        $rules = [
            'title' => ['required', 'array'],
            "title.{$default}" => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:'.Slug::MAX_LENGTH, Rule::unique('contacts', 'slug')->ignore($this->contact()?->id)],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-.\s]*$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'status' => ['required', Rule::in(Contact::statuses())],
            // photo, photo_crop, photo_remove (<x-admin.form.cropped-image>)
            ...CroppedImageInput::rules('photo'),
        ];

        foreach (self::TRANSLATABLE as $field => $max) {
            $rules[$field] ??= ['nullable', 'array'];
            $rules["{$field}.*"] = ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach (['slug', 'phone', 'email', 'latitude', 'longitude', 'photo'] as $field) {
            $attributes[$field] = __("contacts::admin.fields.{$field}");
        }

        foreach (array_keys(self::TRANSLATABLE) as $field) {
            foreach (config('cms.locales') as $locale) {
                $attributes["{$field}.{$locale}"] = __("contacts::admin.fields.{$field}").' ('.strtoupper($locale).')';
            }
        }

        return $attributes;
    }

    /**
     * The validated data, ready for the model: empty translations are left out.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $attributes = [];
        foreach (array_keys(self::TRANSLATABLE) as $field) {
            $attributes[$field] = array_filter((array) $this->validated($field, []), fn ($value) => filled($value));
        }

        $coordinate = fn (string $field) => filled($value = $this->validated($field)) ? round((float) $value, 7) : null;

        return [
            ...$attributes,
            'slug' => $this->validated('slug') ?: null,
            'phone' => $this->validated('phone') ?: null,
            'email' => $this->validated('email') ?: null,
            'latitude' => $coordinate('latitude'),
            'longitude' => $coordinate('longitude'),
            'status' => (string) $this->validated('status'),
        ];
    }
}
