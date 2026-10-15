{{--
    The "Services" widget: a heading (eyebrow = the widget title, slogan, intro), the detail (formatted
    content) and service cards (OdinZero template). $services, $eyebrow, $slogan, $intro, $detail (HTML),
    $link (show "All services").
    A theme can replace it: themes/{theme}/widgets/services-list.blade.php
--}}
@php
    $icons = ['bi-graph-up-arrow', 'bi-lightbulb', 'bi-search-heart', 'bi-bullseye'];
@endphp

<x-theme::section-head :eyebrow="$eyebrow" :slogan="$slogan" :intro="$intro" />

@if ($detail !== '')
    {{-- Purified on save (the widget's "detail" editor field). --}}
    <div class="widget-detail page-body reveal">{!! $detail !!}</div>
@endif

<div class="row g-4 justify-content-center">
    @foreach ($services as $service)
        <div class="col-md-6 col-xl-3 reveal" @if ($loop->index % 4) data-delay="{{ $loop->index % 4 }}" @endif>
            <div class="service-card">
                <span class="num">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div class="icon"><i class="bi {{ $icons[$loop->index % count($icons)] }}"></i></div>
                <h3><a href="{{ $service->url() }}">{{ $service->title }}</a></h3>
                <p>{{ $service->summary(110) }}</p>
                <a href="{{ $service->url() }}" class="link-arrow">{{ __('Read more') }} <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    @endforeach
</div>

@if ($link)
    <div class="text-center mt-5 reveal">
        <a href="{{ route('services.index') }}" class="btn btn-ghost">{{ __('All services') }} <i class="bi bi-arrow-right"></i></a>
    </div>
@endif
