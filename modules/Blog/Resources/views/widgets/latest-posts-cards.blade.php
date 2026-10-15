{{--
    The "Latest posts" widget, layout "cards" ("Featured news & insights" of the OdinZero template): a heading row
    (eyebrow = the widget title, slogan with *highlights*, intro) with "View all posts" beside it, then news cards
    (cover with the category, date, title, "Read more"). A post without a cover gets a drawn placeholder.
    $posts, $showImage, $showDate, $eyebrow, $slogan, $intro.
    A theme can replace it: themes/{theme}/widgets/blog-latest-posts-cards.blade.php
--}}
<div class="row align-items-end mb-5 g-4">
    <div class="col-md-8">
        <x-theme::section-head :eyebrow="$eyebrow" :slogan="$slogan" :intro="$intro" align="start" class="mb-0" />
    </div>
    <div @class(['col-md-4 text-md-end reveal', 'ms-auto' => blank($eyebrow) && blank($slogan) && blank($intro)])>
        <a href="{{ route('blog.index') }}" class="btn btn-ghost">{{ __('blog::widgets.latest_posts.all') }} <i class="bi bi-arrow-right"></i></a>
    </div>
</div>

@if ($posts->isEmpty())
    <p class="text-center mb-0">{{ __('blog::front.empty') }}</p>
@else
    <div class="row g-4">
        @foreach ($posts as $post)
            <div class="col-md-6 col-lg-4 reveal" @if ($loop->index % 3) data-delay="{{ $loop->index % 3 }}" @endif>
                <article class="blog-card">
                    @if ($showImage)
                        <a href="{{ $post->url() }}" class="thumb" @if (! $post->coverUrl()) aria-hidden="true" tabindex="-1" @endif>
                            @if ($cover = $post->coverUrl())
                                <img src="{{ $cover }}" alt="{{ $post->title }}" loading="lazy" decoding="async">
                            @else
                                <span class="blog-card-placeholder"><i class="bi bi-journal-text"></i></span>
                            @endif
                            @if ($post->category)
                                <span class="cat">{{ $post->category->label() }}</span>
                            @endif
                        </a>
                    @endif
                    <div class="body">
                        @if ($showDate && $post->published_at)
                            <div class="meta"><span><i class="bi bi-calendar3"></i><time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->translatedFormat('M d, Y') }}</time></span></div>
                        @endif
                        <h4><a href="{{ $post->url() }}">{{ $post->title }}</a></h4>
                        <a href="{{ $post->url() }}" class="link-arrow">{{ __('Read more') }} <i class="bi bi-arrow-right"></i></a>
                    </div>
                </article>
            </div>
        @endforeach
    </div>
@endif
