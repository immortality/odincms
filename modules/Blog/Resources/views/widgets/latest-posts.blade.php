{{-- The "Latest posts" widget. $posts, $showImage, $showDate --}}
@if ($posts->isEmpty())
    <p class="text-body-secondary small mb-0">{{ __('blog::front.empty') }}</p>
@else
    <ul class="list-unstyled mb-0">
        @foreach ($posts as $post)
            <li class="d-flex align-items-start gap-2 mb-3">
                @if ($showImage && ($cover = $post->coverUrl()))
                    <a href="{{ $post->url() }}" class="flex-shrink-0">
                        <img src="{{ $cover }}" alt="" width="72" height="48" class="rounded" style="object-fit: cover;" loading="lazy">
                    </a>
                @endif
                <div>
                    <a href="{{ $post->url() }}" class="text-decoration-none">{{ $post->title }}</a>
                    @if ($showDate && $post->published_at)
                        <div class="small text-body-secondary">{{ $post->published_at->translatedFormat('j F Y') }}</div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
