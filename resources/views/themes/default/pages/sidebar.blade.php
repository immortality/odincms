{{-- Page layout "sidebar": the text with the "sidebar" widget area on the right. $page, $preview --}}
@extends('themes.default.layouts.app')

@section('title', $page->metaTitle())
@section('meta_description', $page->metaDescription())
@if ($preview)
    @push('head')
        <meta name="robots" content="noindex">
    @endpush
@endif

@section('content')
    <main class="container py-5">
        <div class="row g-5">
            <div class="col-lg-8">
                @include('themes.default.pages.partials.content')
            </div>
            <aside class="col-lg-4">
                @widgetArea('sidebar')
            </aside>
        </div>
    </main>
@endsection
