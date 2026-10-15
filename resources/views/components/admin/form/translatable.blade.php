{{--
    One tab per locale around any field type (input, textarea, editor). Posts name[locale].

    <x-admin.form.translatable name="title" label="Title" :value="$post->getTranslations('title')" required />
    <x-admin.form.translatable name="body" label="Body" type="editor" :value="$post->getTranslations('body')" />

    `required` applies to the default locale only. Tabs with a validation error are marked.
--}}
@props([
    'name',
    'label' => null,
    'type' => 'input',
    'value' => [],
    'locales' => null,
    'required' => false,
    'help' => null,
])

@php
    $locales ??= config('cms.locales');
    $defaultLocale = config('cms.default_locale');
    $value = (array) $value;
    $baseId = \App\Cms\Support\FormField::id($name);
    $firstError = collect($locales)->first(fn ($locale) => $errors->has(\App\Cms\Support\FormField::key($name).'.'.$locale));
    $activeLocale = $firstError ?? $locales[0];
@endphp

<div class="mb-3">
    @if ($label)
        <span class="form-label d-block mb-0">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </span>
    @endif

    <ul class="tabs tabs-sm" role="tablist">
        @foreach ($locales as $locale)
            @php($hasError = $errors->has(\App\Cms\Support\FormField::key($name).'.'.$locale))
            <li role="presentation">
                <button type="button" @class(['tab-link', 'active' => $locale === $activeLocale, 'has-error' => $hasError])
                    data-bs-toggle="tab" data-bs-target="#{{ $baseId }}-{{ $locale }}" role="tab"
                    aria-controls="{{ $baseId }}-{{ $locale }}" aria-selected="{{ $locale === $activeLocale ? 'true' : 'false' }}">
                    {{ strtoupper($locale) }}
                    @if ($locale === $defaultLocale && $required)<span class="req">*</span>@endif
                    @if ($hasError)<i class="fas fa-exclamation-circle"></i>@endif
                </button>
            </li>
        @endforeach
    </ul>

    <div class="tab-content">
        @foreach ($locales as $locale)
            <div @class(['tab-pane', 'active' => $locale === $activeLocale]) id="{{ $baseId }}-{{ $locale }}" role="tabpanel" lang="{{ $locale }}">
                <x-dynamic-component
                    :component="'admin.form.'.$type"
                    :name="$name.'['.$locale.']'"
                    :value="$value[$locale] ?? null"
                    :required="$required && $locale === $defaultLocale"
                    {{ $attributes }} />
            </div>
        @endforeach
    </div>

    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
