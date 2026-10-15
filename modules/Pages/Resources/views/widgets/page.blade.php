{{--
    The "Page" widget: a page as a section — the heading (eyebrow = the widget title, slogan, intro), the
    detail (formatted content) and a link, with the page image beside them. $page, $eyebrow, $slogan,
    $intro, $detail (HTML), $image (URL or null), $link (bool).
    A theme can replace it: themes/{theme}/widgets/pages-page.blade.php
--}}
<div class="row align-items-center g-5 page-widget">
    @if ($image)
        <div class="col-lg-6 order-lg-2">
            <div class="page-widget-art reveal">
                <img src="{{ $image }}" alt="{{ $page->label() }}" loading="lazy" decoding="async">
            </div>
        </div>
    @endif

    <div @class(['col-lg-6' => $image, 'col-lg-8 mx-auto' => ! $image])>
        <x-theme::section-head :eyebrow="$eyebrow" :slogan="$slogan" :intro="$intro" align="start" />

        @if ($detail !== '')
            {{-- Sanitized on save (the widget's "detail" editor field). --}}
            <div class="widget-detail page-body reveal">{!! $detail !!}</div>
        @endif

        @if ($link)
            <div class="reveal">
                <a href="{{ $page->url() }}" class="btn btn-brand">{{ __('Read more') }} <i class="bi bi-arrow-right"></i></a>
            </div>
        @endif
    </div>
</div>
