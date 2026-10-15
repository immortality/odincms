{{-- Page layout "full-width": the whole container width. $page, $preview --}}
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
        @include('themes.default.pages.partials.content')
    </main>
@endsection
