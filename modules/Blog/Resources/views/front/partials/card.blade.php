{{-- A post in the blog list. $post --}}
<article class="card mb-4 border-0 border-bottom rounded-0 pb-4">
    <div class="row g-3">
        @if ($cover = $post->coverUrl())
            <div class="col-sm-4">
                <a href="{{ $post->url() }}"><img src="{{ $cover }}" alt="{{ $post->title }}" class="img-fluid rounded" loading="lazy"></a>
            </div>
        @endif
        <div @class(['col-sm-8' => $cover, 'col-12' => ! $cover])>
            <h2 class="h4 mb-1"><a href="{{ $post->url() }}" class="text-decoration-none">{{ $post->title }}</a></h2>
            <div class="small text-body-secondary mb-2">
                <time datetime="{{ $post->published_at?->toAtomString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time>
                @if ($post->category)
                    · <a href="{{ route('blog.category', $post->category->slug) }}" class="text-body-secondary">{{ $post->category->label() }}</a>
                @endif
            </div>
            <p class="mb-2">{{ $post->summary() }}</p>
            <a href="{{ $post->url() }}">{{ __('blog::front.read_more') }} <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
</article>
