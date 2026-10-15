@extends('themes.default.layouts.app')

@section('content')
    <main class="container py-5 text-center">
        <i class="bi bi-cone-striped display-1 text-warning"></i>
        <h1 class="display-6 fw-bold mt-3">{{ $siteName }}</h1>
        <p class="lead text-body-secondary">{{ $message !== '' ? $message : __('We are making some improvements. Please check back soon.') }}</p>
    </main>
@endsection
