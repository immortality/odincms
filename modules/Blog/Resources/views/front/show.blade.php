{{-- One post. $post, $preview (a draft or scheduled post shown to an editor) --}}
@extends('themes.'.config('cms.theme').'.layouts.app')

@section('title', $post->metaTitle())
@section('meta_description', $post->metaDescription())
@section('og_type', 'article')
@if ($cover = $post->coverUrl('large'))
    @section('og_image', $cover)
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
                    <div class="alert alert-warning">
                        {{ $post->isScheduled() ? __('blog::front.scheduled', ['date' => $post->published_at?->translatedFormat('j F Y, H:i')]) : __('blog::front.draft') }}
                    </div>
                @endif

                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('blog::front.home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">{{ __('blog::front.title') }}</a></li>
                        @if ($post->category)
                            <li class="breadcrumb-item"><a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->label() }}</a></li>
                        @endif
                    </ol>
                </nav>

                <h1 class="mb-2">{{ $post->title }}</h1>
                <div class="text-body-secondary mb-4">
                    @if ($post->published_at)
                        <time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>
                    @endif
                    @if ($post->author)
                        · {{ $post->author->name }}
                    @endif
                </div>

                @if ($cover)
                    <img src="{{ $cover }}" alt="{{ $post->title }}" class="img-fluid rounded mb-4">
                @endif

                @if ($post->excerpt)
                    <p class="lead">{{ $post->excerpt }}</p>
                @endif

                {{-- Purified on save (Post::purifiedAttributes). --}}
                <div class="post-body">{!! $post->body !!}</div>

                <p class="mt-5"><a href="{{ route('blog.index') }}"><i class="bi bi-arrow-left"></i> {{ __('blog::front.back') }}</a></p>
            </article>
        </div>
    </main>
@endsection
