{{--
    A section heading of the OdinZero template: the eyebrow (small label), the slogan (h2) and the intro.
    Words of the slogan wrapped in *asterisks* get the highlight ("We do *world-class* work").
    Each part is optional; nothing is output when all are empty.

    <x-theme::section-head :eyebrow="$title" :slogan="$slogan" :intro="$intro" />           centred, above a grid
    <x-theme::section-head :eyebrow="$title" :slogan="$slogan" :intro="$intro" align="start" />  in a text column
--}}
@props(['eyebrow' => '', 'slogan' => '', 'intro' => '', 'align' => 'center'])

@php
    // Escape first, then turn *words* into the highlight.
    $sloganHtml = preg_replace('/\*([^*]+)\*/', '<span class="hl">$1</span>', e((string) $slogan));
@endphp

@if (filled($eyebrow) || filled($slogan) || filled($intro))
    <div {{ $attributes->class(['section-head' => $align === 'center', 'section-head-start' => $align !== 'center', 'reveal']) }}>
        @if (filled($eyebrow))
            <span class="eyebrow">{{ $eyebrow }}</span>
        @endif
        @if (filled($slogan))
            <h2 class="section-title">{!! $sloganHtml !!}</h2>
        @endif
        @if (filled($intro))
            <p @class(['mb-4' => $align !== 'center'])>{{ $intro }}</p>
        @endif
    </div>
@endif
