{{-- The blog, or one category of it. $posts (paginated), $category (or null), $categories (tree by parent id) --}}
@extends('themes.'.config('cms.theme').'.layouts.app')

@section('title', $category?->label() ?? __('blog::front.title'))
@if ($category?->description)
    @section('meta_description', \Illuminate\Support\Str::limit($category->description, 160))
@endif

@section('content')
    <main class="container py-5">
        <div class="row g-5">
            <div class="col-lg-8">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('blog::front.home') }}</a></li>
                        @if ($category)
                            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">{{ __('blog::front.title') }}</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $category->label() }}</li>
                        @else
                            <li class="breadcrumb-item active" aria-current="page">{{ __('blog::front.title') }}</li>
                        @endif
                    </ol>
                </nav>

                <h1 class="mb-3">{{ $category?->label() ?? __('blog::front.title') }}</h1>
                @if ($category?->description)
                    <p class="lead text-body-secondary">{{ $category->description }}</p>
                @endif

                @forelse ($posts as $post)
                    @include('blog::front.partials.card', ['post' => $post])
                @empty
                    <p class="text-body-secondary py-4">{{ __('blog::front.empty') }}</p>
                @endforelse

                {{ $posts->links('pagination::bootstrap-5') }}
            </div>

            <aside class="col-lg-4">
                @if ($categories->isNotEmpty())
                    <div class="mb-4">
                        <h2 class="h5">{{ __('blog::front.categories') }}</h2>
                        @include('blog::front.partials.categories', ['parentId' => 0, 'current' => $category])
                    </div>
                @endif

                @widgetArea('sidebar')
            </aside>
        </div>
    </main>
@endsection
