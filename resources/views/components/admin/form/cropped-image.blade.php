{{--
    An image chosen by drag and drop (or Select) and cropped in a modal to the module's size
    (module.json → "images", Admin → Settings → Images). Needs enctype="multipart/form-data".

    <x-admin.form.cropped-image name="photo" :model="$service" :label="__('services::admin.fields.photo')" />

    Posts {name} (the original file), {name}_crop (the crop area) and {name}_remove; see
    App\Cms\Media\CroppedImageInput. The script is resources/js/admin/cropped-image.js (it uses the
    data-* hooks below); the styles are in resources/css/admin.scss.
--}}
@props([
    'name',
    'model',
    'label' => null,
    'help' => null,
])

@php
    $spec = $model->imageSpec($name);
    $media = $model->exists ? $model->getFirstMedia($name) : null;
    $id = \App\Cms\Support\FormField::id($name);
    $maxMb = (int) (\App\Cms\Media\CroppedImageInput::MAX_KB / 1024);
    $strings = __('cms.image');
@endphp

@if ($spec === null)
    <div class="alert-soft danger" role="alert">
        <i class="fas fa-times-circle"></i>
        <div class="alert-body">Image "{{ $name }}" has no size: add it to "images" in the module.json.</div>
    </div>
@else
    <div class="mb-3 cropped-image" data-cropped-image
         data-crop-width="{{ $spec->width }}" data-crop-height="{{ $spec->height }}" data-max-kb="{{ \App\Cms\Media\CroppedImageInput::MAX_KB }}"
         data-original="{{ $media?->getUrl() }}" data-strings='@json($strings)'>
        @if ($label)
            <label class="form-label" for="{{ $id }}">{{ $label }}</label>
        @endif

        <input type="file" name="{{ $name }}" id="{{ $id }}" accept="image/jpeg,image/png,image/webp,image/gif" class="d-none" data-file>
        <input type="hidden" name="{{ $name }}_crop" value="{{ $media?->getCustomProperty('crop') ? json_encode($media->getCustomProperty('crop')) : '' }}" data-crop>
        <input type="hidden" name="{{ $name }}_remove" value="0" data-remove>

        {{-- The current or newly cropped image --}}
        <div class="cropped-image-preview" data-preview @if (! $media) hidden @endif>
            <img src="{{ $media ? $model->imageUrl($name, 'large') : '' }}" alt="" data-preview-image>
            <div class="cropped-image-actions">
                <button type="button" class="btn-soft btn-sm" data-action="change"><i class="fas fa-upload"></i> {{ $strings['change'] }}</button>
                <button type="button" class="btn-soft btn-sm" data-action="recrop"><i class="fas fa-crop-alt"></i> {{ $strings['recrop'] }}</button>
                <button type="button" class="btn-soft btn-sm danger" data-action="remove"><i class="far fa-trash-alt"></i> {{ $strings['remove'] }}</button>
            </div>
        </div>

        {{-- Drop zone (OdinAdmin .dz-area; the drag-over state is .dz-drag-hover) --}}
        <div class="dz-area dz-compact" data-drop @if ($media) hidden @endif tabindex="0" role="button" aria-controls="{{ $id }}">
            <div class="dz-message">
                <div class="dz-ico"><i class="far fa-image"></i></div>
                <span class="dz-title">{{ $strings['drop'] }} {{ $strings['or'] }} <span>{{ $strings['select'] }}</span></span>
            </div>
        </div>

        <div class="alert-soft light alert-sm mt-2" data-removed hidden>
            <i class="far fa-trash-alt"></i>
            <div class="alert-body">{{ $strings['removed'] }} <a href="#" class="ms-1" data-action="undo">{{ $strings['undo'] }}</a></div>
        </div>
        <div class="invalid-feedback d-block" data-error hidden></div>

        @error($name)
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        <div class="form-text">{{ $help ?? __('cms.image.size_note', ['size' => $spec->dimensions(), 'max' => $maxMb]) }}</div>

        {{-- The crop modal (moved to <body> by the script) --}}
        <div class="modal fade" tabindex="-1" aria-hidden="true" data-modal data-bs-backdrop="static">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $strings['crop_title'] }} <span class="tbl-sub d-inline ms-1">{{ $spec->dimensions() }} px</span></h5>
                        <button type="button" class="modal-close" data-action="cancel" aria-label="{{ $strings['cancel'] }}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <div class="cropped-image-stage"><img src="" alt="" data-crop-image></div>
                        <div class="alert-soft warning alert-sm mt-3" data-too-small hidden>
                            <i class="fas fa-exclamation-triangle"></i><span class="alert-body"></span>
                        </div>
                        <p class="form-text mb-0">{{ $strings['crop_hint'] }}</p>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <div class="d-flex gap-1">
                            <button type="button" class="icon-btn" data-action="zoom-out" title="{{ $strings['zoom_out'] }}" aria-label="{{ $strings['zoom_out'] }}"><i class="fas fa-search-minus"></i></button>
                            <button type="button" class="icon-btn" data-action="zoom-in" title="{{ $strings['zoom_in'] }}" aria-label="{{ $strings['zoom_in'] }}"><i class="fas fa-search-plus"></i></button>
                            <button type="button" class="icon-btn" data-action="reset" title="{{ $strings['reset'] }}" aria-label="{{ $strings['reset'] }}"><i class="fas fa-undo"></i></button>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn-soft" data-action="cancel">{{ $strings['cancel'] }}</button>
                            <button type="button" class="btn-brand" data-action="apply"><i class="fas fa-check"></i> {{ $strings['apply'] }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
