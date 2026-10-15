{{--
    Several translatable fields under one set of language tabs: each tab holds all the fields in that
    language (e.g. EN: title + detail, RU: title + detail), so one language is filled in at a time.
    Posts name[locale] for each field, like <x-admin.form.translatable>.

    <x-admin.form.translatable-group :fields="[
        ['name' => 'title', 'label' => __('services::admin.fields.title'), 'value' => $item->getTranslations('title'), 'required' => true],
        ['name' => 'detail', 'label' => __('services::admin.fields.detail'), 'type' => 'editor', 'value' => $item->getTranslations('detail')],
    ]" />

    Field keys: name, label, value (translations by locale), type (input | textarea | editor, default input),
    required (the default locale only), help, rows (textarea). A tab with a validation error is marked
    and opened first.
--}}
@props([
    'fields',
    'locales' => null,
    'label' => null,
])

@php
    $locales ??= (array) config('cms.locales');
    $defaultLocale = config('cms.default_locale');
    $fields = array_map(fn (array $field) => $field + ['type' => 'input', 'value' => [], 'required' => false, 'help' => null, 'rows' => 4], $fields);
    $baseId = \App\Cms\Support\FormField::id(implode('-', array_column($fields, 'name')));

    $hasError = fn (string $locale) => collect($fields)->contains(
        fn (array $field) => $errors->has(\App\Cms\Support\FormField::key($field['name']).'.'.$locale)
    );
    $activeLocale = collect($locales)->first($hasError) ?? $locales[0];
    $required = collect($fields)->contains('required', true);
@endphp

<div class="mb-3">
    @if ($label)
        <span class="form-label d-block mb-0">{{ $label }}</span>
    @endif

    <ul class="tabs tabs-sm" role="tablist">
        @foreach ($locales as $locale)
            <li role="presentation">
                <button type="button" @class(['tab-link', 'active' => $locale === $activeLocale, 'has-error' => $hasError($locale)])
                    data-bs-toggle="tab" data-bs-target="#{{ $baseId }}-{{ $locale }}" role="tab"
                    aria-controls="{{ $baseId }}-{{ $locale }}" aria-selected="{{ $locale === $activeLocale ? 'true' : 'false' }}">
                    {{ config("cms.locale_names.{$locale}", strtoupper($locale)) }}
                    @if ($locale === $defaultLocale && $required)<span class="req">*</span>@endif
                    @if ($hasError($locale))<i class="fas fa-exclamation-circle"></i>@endif
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($locales as $locale)
            <div @class(['tab-pane', 'active' => $locale === $activeLocale]) id="{{ $baseId }}-{{ $locale }}" role="tabpanel" lang="{{ $locale }}">
                @foreach ($fields as $field)
                    @php
                        $name = $field['name'].'['.$locale.']';
                        $value = ((array) $field['value'])[$locale] ?? null;
                        $isRequired = $field['required'] && $locale === $defaultLocale;
                    @endphp

                    @switch($field['type'])
                        @case('editor')
                            <x-admin.form.editor :name="$name" :label="$field['label']" :value="$value" :required="$isRequired" :help="$field['help']" />
                            @break
                        @case('textarea')
                            <x-admin.form.textarea :name="$name" :label="$field['label']" :value="$value" :required="$isRequired" :help="$field['help']" :rows="$field['rows']" />
                            @break
                        @default
                            <x-admin.form.input :name="$name" :label="$field['label']" :value="$value" :required="$isRequired" :help="$field['help']" />
                    @endswitch
                @endforeach
            </div>
        @endforeach
    </div>
</div>
