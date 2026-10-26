{{--
    <x-admin.form.select name="status" label="Status" :options="['draft' => 'Draft', 'published' => 'Published']" :value="$post->status" />
    searchable → Tom Select (data-ts, odinadmin/js/forms.js; the library is loaded once by this component)
    multiple → posts name[]
--}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'multiple' => false,
    'searchable' => false,
    'required' => false,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
    $selected = array_map('strval', (array) old($key, $value));
    $inputName = $multiple && ! str_ends_with($name, '[]') ? $name.'[]' : $name;
@endphp

@if ($searchable)
    {{-- Same keys as <x-admin.form.tree-select>: the library loads once per page. --}}
    @pushOnce('admin-vendor-styles', 'tom-select-css')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.css">
    @endPushOnce
    @pushOnce('admin-vendor', 'tom-select-js')
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js"></script>
    @endPushOnce
@endif

<div class="mb-3">
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </label>
    @endif
    <select name="{{ $inputName }}" id="{{ $id }}" @required($required) @if ($multiple) multiple @endif
        @if ($searchable) data-ts="default" @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif @if (! $multiple && ! $required) data-clear="true" @endif @endif
        {{-- Tom Select copies the select's classes onto its wrapper, and .form-select would frame the themed
             control a second time (odinadmin/css/vendor/tom-select.css styles .ts-wrapper itself). --}}
        {{ $attributes->class(['form-select form-soft' => ! $searchable, 'is-invalid' => $errors->has($key)]) }}>
        @if ($placeholder && ! $multiple)
            <option value="">{{ $searchable ? '' : $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(in_array((string) $optionValue, $selected, true))>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
