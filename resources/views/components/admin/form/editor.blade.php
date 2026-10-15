{{--
    TinyMCE editor (self-hosted, bundled by Vite and loaded only on pages that use it: resources/js/admin/editor.js).
    <x-admin.form.editor name="body" label="Body" :value="$post->body" />   mode="basic" for a small toolbar.
    The editor accepts any markup (classes, data-*, inline SVG, icon tags); the HTML is cleaned on save by
    App\Cms\Support\HtmlSanitizer (PurifiesHtml on models, FormFields for settings).
--}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'mode' => 'full',
    'height' => null,
    'help' => null,
    'required' => false,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
@endphp

<div @class(['mb-3 editor-field', 'is-invalid' => $errors->has($key)])>
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" data-editor="{{ $mode }}" @if ($height) data-height="{{ $height }}" @endif
        {{ $attributes->class(['form-control form-soft']) }}>{{ old($key, $value) }}</textarea>
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
