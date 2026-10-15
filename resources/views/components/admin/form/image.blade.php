{{--
    <x-admin.form.image name="cover" label="Cover image" :current="$post->getFirstMediaUrl('cover', 'thumb')" />
    The form needs enctype="multipart/form-data". When removable, a checked "{name}_remove" box asks to delete the current image.
    The preview of a newly chosen file uses Alpine (resources/js/admin.js).
--}}
@props([
    'name',
    'label' => null,
    'current' => null,
    'accept' => 'image/*',
    'removable' => true,
    'help' => null,
    'id' => null,
])

@php
    $key = \App\Cms\Support\FormField::key($name);
    $id ??= \App\Cms\Support\FormField::id($name);
@endphp

<div class="mb-3" x-data="{ preview: @js($current ?: null) }">
    @if ($label)
        <label class="form-label" for="{{ $id }}">{{ $label }}</label>
    @endif

    <div class="d-flex align-items-start gap-3">
        <template x-if="preview">
            <img :src="preview" alt="" class="file-preview">
        </template>

        <div class="flex-grow-1" style="min-width:0;">
            <input type="file" name="{{ $name }}" id="{{ $id }}" accept="{{ $accept }}"
                {{ $attributes->class(['form-control form-soft', 'is-invalid' => $errors->has($key)]) }}
                @change="const file = $event.target.files[0]; if (file) preview = URL.createObjectURL(file)">

            @if ($removable && $current)
                <label class="check-row mt-2 mb-0" for="{{ $id }}-remove">
                    <input type="checkbox" class="form-check-input check-soft" name="{{ $name }}_remove" id="{{ $id }}-remove" value="1">
                    {{ __('Remove current image') }}
                </label>
            @endif
        </div>
    </div>

    @error($key)
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @if ($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
