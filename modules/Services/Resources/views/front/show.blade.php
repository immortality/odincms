{{-- One service. $service, $preview (a draft shown to an editor) --}}
@extends('themes.'.config('cms.theme').'.layouts.app')

@section('title', $service->metaTitle())
@section('meta_description', $service->metaDescription())
@if ($photo = $service->photoUrl('large'))
    @section('og_image', $photo)
@endif

@if ($preview)
    @push('head')
        <meta name="robots" content="noindex">
    @endpush
@endif

@section('content')
    <main class="container py-5">
        <div class="row justify-content-center">
            <article class="col-lg-8">
                @if ($preview)
                    <div class="alert alert-warning">{{ __('services::front.draft') }}</div>
                @endif

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('services::front.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('services.index') }}">{{ __('services::front.title') }}</a></li>
                    </ol>
                </nav>

                <h1 class="mb-4">{{ $service->title }}</h1>

                @if ($photo)
                    <img src="{{ $photo }}" alt="{{ $service->title }}" class="img-fluid rounded mb-4">
                @endif

                {{-- Purified on save (Service::purifiedAttributes). --}}
                <div class="post-body">{!! $service->detail !!}</div>

                <p class="mt-5"><a href="{{ route('services.index') }}"><i class="bi bi-arrow-left"></i> {{ __('services::front.back') }}</a></p>
            </article>
        </div>
    </main>
@endsection
