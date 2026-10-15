{{-- Page layout "default": a narrow, centered column. $page, $preview --}}
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @include('themes.default.pages.partials.content')
            </div>
        </div>
    </main>
@endsection
