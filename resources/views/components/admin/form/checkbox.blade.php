{{--
    <x-admin.form.checkbox name="is_active" label="Active" :checked="$user->is_active" />
    Posts 1 when checked and 0 when not (hidden input). switch=false renders a plain checkbox.
--}}
@props([
    'name',
    'label' => null,
    'checked' => false,
    'value' => 1,
    'switch' => true,
    'help' => null,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
    $isChecked = session()->hasOldInput() ? (string) old($key) === (string) $value : (bool) $checked;
@endphp

<div class="mb-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <label @class(['check-row mb-0', 'form-switch' => $switch]) for="{{ $id }}">
        <input type="checkbox" name="{{ $name }}" id="{{ $id }}" value="{{ $value }}" @checked($isChecked) @if ($switch) role="switch" @endif
            {{ $attributes->class(['form-check-input check-soft', 'is-invalid' => $errors->has($key)]) }}>
        {{ $label }}
    </label>
    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
