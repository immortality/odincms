{{--
    A parent select for a tree (pages, categories): Tom Select data-ts="tree" (odinadmin/js/forms.js) shows the
    options indented by depth, and the chosen one as its trail ("About us / Team").

    <x-admin.form.tree-select name="parent_id" :label="__('pages::admin.fields.parent')" :value="$page->parent_id" :placeholder="__('pages::admin.top_level')"
        :options="[['value' => 6, 'label' => 'Team', 'depth' => 1, 'trail' => 'About us / Team', 'data' => ['slug' => 'about/team']], …]" />

    Options in tree order (each child right below its parent). "data" adds data-* attributes to the option.
    Not required → empty choice (the placeholder) and a clear button.
--}}
@props([
    'name',
    'label' => null,
    'options' => [],
    'value' => null,
    'placeholder' => null,
    'help' => null,
    'required' => false,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
    $selected = (string) old($key, $value);
@endphp

@pushOnce('admin-vendor-styles', 'tom-select-css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/css/tom-select.css">
@endPushOnce
@pushOnce('admin-vendor', 'tom-select-js')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.6.2/dist/js/tom-select.complete.min.js"></script>
@endPushOnce

<div class="mb-3">
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" @required($required) data-ts="tree"
        @if ($placeholder) data-placeholder="{{ $placeholder }}" @endif @unless ($required) data-clear="true" @endunless
        {{ $attributes->class(['is-invalid' => $errors->has($key)]) }}>
        @unless ($required)
            <option value="">{{ $placeholder }}</option>
        @endunless
        @foreach ($options as $option)
            <option value="{{ $option['value'] }}" data-depth="{{ $option['depth'] ?? 0 }}" data-path="{{ $option['trail'] ?? $option['label'] }}"
                @foreach ($option['data'] ?? [] as $dataKey => $dataValue) data-{{ $dataKey }}="{{ $dataValue }}" @endforeach
                @selected((string) $option['value'] === $selected)>{{ $option['label'] }}</option>
        @endforeach
    </select>
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
