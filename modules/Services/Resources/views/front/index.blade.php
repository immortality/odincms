{{-- The services. $services (paginated) --}}
@extends('themes.'.config('cms.theme').'.layouts.app')

@section('title', __('services::front.title'))

@section('content')
    <main class="container py-5">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('services::front.home') }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ __('services::front.title') }}</li>
            </ol>
        </nav>

        <h1 class="mb-4">{{ __('services::front.title') }}</h1>

        @if ($services->isEmpty())
            <p class="text-body-secondary py-4">{{ __('services::front.empty') }}</p>
        @else
            <div class="row g-4">
                @foreach ($services as $service)
                    <div class="col-sm-6 col-lg-4">
                        <article class="card h-100 shadow-sm">
                            @if ($photo = $service->photoUrl())
                                <a href="{{ $service->url() }}"><img src="{{ $photo }}" alt="{{ $service->title }}" class="card-img-top" loading="lazy"></a>
                            @endif
                            <div class="card-body d-flex flex-column">
                                <h2 class="h5 card-title"><a href="{{ $service->url() }}" class="text-decoration-none stretched-link">{{ $service->title }}</a></h2>
                                <p class="card-text text-body-secondary mb-0">{{ $service->summary(140) }}</p>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">{{ $services->links('pagination::bootstrap-5') }}</div>
        @endif
    </main>
@endsection
