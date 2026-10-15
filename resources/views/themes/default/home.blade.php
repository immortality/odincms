{{--
    The home page: two widget areas, filled in Admin → Widgets.
    - "hero": a Hero banner or a Banner slider (Banners module), full width.
    - "home": the page's sections, in the order set in the admin. Each widget becomes a section with
      its title as the heading (themes.default.partials.home-area).
--}}
@extends('themes.default.layouts.app')

@php
    $tagline = \App\Cms\Support\Facades\Settings::translated('site.tagline');
@endphp

@if ($tagline !== '')
    @section('meta_description', $tagline)
@endif

@section('content')
    <main>
        @widgetArea('hero', 'themes.default.partials.bare-area')
        @widgetArea('home', 'themes.default.partials.home-area')
    </main>
@endsection
