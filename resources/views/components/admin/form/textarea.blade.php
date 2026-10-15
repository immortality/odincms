{{-- <x-admin.form.textarea name="excerpt" label="Excerpt" :value="$post->excerpt" rows="3" /> --}}
@props([
    'name',
    'label' => null,
    'value' => null,
    'help' => null,
    'rows' => 4,
    'required' => false,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
@endphp

<div class="mb-3">
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" @required($required)
        {{ $attributes->class(['form-control form-soft', 'is-invalid' => $errors->has($key)]) }}>{{ old($key, $value) }}</textarea>
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
