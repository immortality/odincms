{{--
    The inside of a hero (OdinZero template, html/index6.html): badge, title + highlight, text, button,
    and the image with up to two chips. $banner, $animate (scroll-in animation; off inside the slider,
    where hidden slides would never be revealed), $heading (h1 for the first banner, h2 for the others;
    both styled by .hero-title)
--}}
@php
    $image = $banner->imageUrlFor('large');
    $heading ??= 'h1';
@endphp

<div class="row align-items-center g-5">
    <div @class(['col-lg-6' => $image, 'col-lg-8' => ! $image])>
        @if ($banner->badge_label || $banner->badge_text)
            <div @class(['hero-badge', 'reveal' => $animate])>
                @if ($banner->badge_label)<span>{{ $banner->badge_label }}</span>@endif
                {{ $banner->badge_text }}
            </div>
        @endif

        <{{ $heading }} @class(['hero-title', 'reveal' => $animate]) @if ($animate) data-delay="1" @endif>
            {{ $banner->title }} @if ($banner->highlight)<span class="grad">{{ $banner->highlight }}</span>@endif
        </{{ $heading }}>

        @if ($banner->text)
            <p @class(['lead', 'reveal' => $animate]) @if ($animate) data-delay="2" @endif>{{ $banner->text }}</p>
        @endif

        @if ($banner->button_label && $banner->button_url)
            <div @class(['hero-cta', 'reveal' => $animate]) @if ($animate) data-delay="3" @endif>
                <a href="{{ $banner->button_url }}" class="btn btn-brand">{{ $banner->button_label }} <i class="bi bi-arrow-right"></i></a>
            </div>
        @endif
    </div>

    @if ($image)
        <div class="col-lg-6">
            <div @class(['hero-art', 'reveal' => $animate]) @if ($animate) data-delay="2" @endif>
                {{-- The first banner's image is the page's main image (LCP): load it first; later slides lazily. --}}
                <img src="{{ $image }}" alt="{{ $banner->label() }}" decoding="async"
                     @if ($heading === 'h1') fetchpriority="high" @else loading="lazy" @endif>
                @foreach ($banner->chips() as $chip)
                    <div class="hero-chip c{{ $loop->iteration }} d-none d-sm-flex"><i class="bi {{ $chip['icon'] }}"></i>{{ $chip['text'] }}</div>
                @endforeach
            </div>
        </div>
    @endif
</div>
