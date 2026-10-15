{{--
    A field of the sign-in style forms (OdinAdmin, reference: odinadmin/login2.html).

    <x-admin.auth.input name="email" type="email" :label="__('Email')" icon="far fa-envelope"
        :feedback="__('Please enter your email address.')" autocomplete="username" required autofocus />

    type="password" adds the show / hide button. feedback: the browser-check message; a server error
    for the field replaces it and marks the field invalid. Slot "link": shown on the right of the label.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'icon' => null,
    'feedback' => null,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
    $current = $type === 'password' ? null : old($key, $value);
    $error = $errors->first($key);
    $message = $error ?: $feedback;
@endphp

<div class="form-group">
    <div class="form-label-row">
        <label class="form-label" for="{{ $id }}">{{ $label }}</label>
        {{ $link ?? '' }}
    </div>

    @if ($type === 'password')
        <div class="input-action icon-lg">
            <input type="password" name="{{ $name }}" id="{{ $id }}"
                {{ $attributes->class(['form-control form-soft form-lg', 'is-invalid' => $error]) }}>
            <button type="button" class="input-action-btn" data-toggle-password="#{{ $id }}" aria-label="{{ __('Show password') }}"><i class="far fa-eye"></i></button>
            @if ($message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endif
        </div>
    @else
        <div @class(['input-icon icon-end icon-lg' => $icon])>
            <input type="{{ $type }}" name="{{ $name }}" id="{{ $id }}" value="{{ $current }}"
                {{ $attributes->class(['form-control form-soft form-lg', 'is-invalid' => $error]) }}>
            @if ($icon)
                <i class="{{ $icon }}" aria-hidden="true"></i>
            @endif
            @if ($message)
                <div class="invalid-feedback">{{ $message }}</div>
            @endif
        </div>
    @endif
</div>
