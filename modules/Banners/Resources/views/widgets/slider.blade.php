{{--
    The "Banner slider" widget: a Bootstrap carousel of banners. $banners, $interval (seconds, 0 = no
    autoplay), $arrows, $dots. A theme can replace it: themes/{theme}/widgets/banners-slider.blade.php
--}}
@php
    $id = 'hero-slider-'.$banners->first()->id.'-'.$banners->count();
@endphp

<section class="hero hero-slider">
    <div id="{{ $id }}" class="carousel slide carousel-fade"
         @if ($interval > 0) data-bs-ride="carousel" data-bs-interval="{{ $interval * 1000 }}" @else data-bs-interval="false" @endif
         aria-roledescription="carousel">
        <div class="carousel-inner">
            @foreach ($banners as $banner)
                <div @class(['carousel-item', 'active' => $loop->first]) aria-roledescription="slide"
                     aria-label="{{ $loop->iteration }} / {{ $loop->count }}">
                    <div class="container">
                        @include('banners::widgets.partials.content', [
                            'banner' => $banner,
                            'animate' => false,
                            'heading' => $loop->first ? 'h1' : 'h2',
                        ])
                    </div>
                </div>
            @endforeach
        </div>

        @if ($arrows || $dots)
            <div class="container">
                <div class="hero-slider-nav">
                    @if ($arrows)
                        <button type="button" data-bs-target="#{{ $id }}" data-bs-slide="prev" aria-label="{{ __('Previous') }}"><i class="bi bi-arrow-left"></i></button>
                    @endif
                    @if ($dots)
                        {{-- .carousel-indicators: Bootstrap moves "active" between these. --}}
                        <div class="carousel-indicators hero-slider-dots">
                            @foreach ($banners as $banner)
                                <button type="button" data-bs-target="#{{ $id }}" data-bs-slide-to="{{ $loop->index }}"
                                    @class(['active' => $loop->first]) @if ($loop->first) aria-current="true" @endif
                                    aria-label="{{ $loop->iteration }} / {{ $loop->count }}"></button>
                            @endforeach
                        </div>
                    @endif
                    @if ($arrows)
                        <button type="button" data-bs-target="#{{ $id }}" data-bs-slide="next" aria-label="{{ __('Next') }}"><i class="bi bi-arrow-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</section>
