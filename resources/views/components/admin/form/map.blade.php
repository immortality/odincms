{{--
    Pick a point on a map (Leaflet + OpenStreetMap, resources/js/admin/map-picker.js): click the map or drag the
    marker; the latitude / longitude fields can also be typed. Posts two numbers (or two empty values).

    <x-admin.form.map :label="__('contacts::admin.fields.coordinates')" :latitude="$contact->latitude" :longitude="$contact->longitude" />

    Names default to "latitude" and "longitude". The start view comes from config('cms.map').
--}}
@props([
    'label' => null,
    'latitudeName' => 'latitude',
    'longitudeName' => 'longitude',
    'latitude' => null,
    'longitude' => null,
    'help' => null,
    'height' => 320,
])

@php
    $id = \App\Cms\Support\FormField::id($latitudeName.'-'.$longitudeName);
    $lat = old($latitudeName, $latitude);
    $lng = old($longitudeName, $longitude);
    $map = (array) config('cms.map');
    $error = $errors->first($latitudeName) ?: $errors->first($longitudeName);
@endphp

<div class="mb-3 map-picker" data-map-picker
     data-center='@json($map['center'] ?? [0, 0])' data-zoom="{{ $map['zoom'] ?? 12 }}"
     data-tiles="{{ $map['tiles'] ?? '' }}" data-attribution="{{ $map['attribution'] ?? '' }}">
    @if ($label)
        <span class="form-label d-block">{{ $label }}</span>
    @endif

    <div class="map-picker-map" style="height: {{ (int) $height }}px;" data-map
         role="application" aria-label="{{ __('Map: click to place the marker') }}"></div>

    <div class="row g-2 mt-1 align-items-end">
        <div class="col-sm">
            <label class="form-label" for="{{ $id }}-lat">{{ __('Latitude') }}</label>
            <input type="text" inputmode="decimal" name="{{ $latitudeName }}" id="{{ $id }}-lat" value="{{ $lat }}" placeholder="42.8746"
                @class(['form-control form-soft', 'is-invalid' => $errors->has($latitudeName)]) data-lat>
        </div>
        <div class="col-sm">
            <label class="form-label" for="{{ $id }}-lng">{{ __('Longitude') }}</label>
            <input type="text" inputmode="decimal" name="{{ $longitudeName }}" id="{{ $id }}-lng" value="{{ $lng }}" placeholder="74.5698"
                @class(['form-control form-soft', 'is-invalid' => $errors->has($longitudeName)]) data-lng>
        </div>
        <div class="col-sm-auto">
            <button type="button" class="btn-soft w-100 justify-content-center" data-map-clear>
                <i class="fas fa-times"></i> {{ __('Clear') }}
            </button>
        </div>
    </div>

    @if ($error)
        <div class="invalid-feedback d-block">{{ $error }}</div>
    @endif
    <div class="form-text">{{ $help ?? __('Click the map or drag the marker to set the point.') }}</div>
</div>
