{{-- An error page in the theme (see resources/views/errors). $code, $title, $text --}}
@extends('themes.default.layouts.app')

@section('title', $title)

@push('head')
    <meta name="robots" content="noindex">
@endpush

@section('content')
    <main class="container py-5 text-center">
        <p class="display-1 fw-bold text-primary mb-0">{{ $code }}</p>
        <h1 class="h3 mb-3">{{ $title }}</h1>
        <p class="lead text-body-secondary">{{ $text }}</p>
        <a href="{{ url('/') }}" class="btn btn-brand mt-2">{{ __('Back to the home page') }} <i class="bi bi-arrow-right"></i></a>
    </main>
@endsection
