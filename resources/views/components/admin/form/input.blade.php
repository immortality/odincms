{{--
    <x-admin.form.input name="title" label="Title" :value="$post->title" required />
    icon: Font Awesome classes shown inside the field ("fas fa-link"). type="password" gets a show / hide button.
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'help' => null,
    'icon' => null,
    'required' => false,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
    $current = $type === 'password' ? null : old($key, $value);
    $wrap = $type === 'password' ? 'input-action' : ($icon ? 'input-icon' : null);
@endphp

<div class="mb-3">
    @if ($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }} @if ($required)<span class="req">*</span>@endif
        </label>
    @endif

    @if ($wrap)<div class="{{ $wrap }}">@endif
        @if ($icon && $type !== 'password')
            <i class="{{ $icon }}"></i>
        @endif
        <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" @if ($type !== 'password') value="{{ $current }}" @endif @required($required)
            {{ $attributes->class(['form-control form-soft', 'is-invalid' => $errors->has($key)]) }}>
        @if ($type === 'password')
            <button type="button" class="input-action-btn" data-toggle-password="#{{ $id }}" aria-label="{{ __('Show password') }}"><i class="far fa-eye"></i></button>
        @endif
    @if ($wrap)</div>@endif

    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
