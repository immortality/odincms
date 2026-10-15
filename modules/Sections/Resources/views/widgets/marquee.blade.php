{{--
    The "Marquee" widget: a full-width lime ribbon of words scrolling in a loop (CSS only,
    resources/css/theme/_sections.scss). The list is output twice for a seamless loop; screen readers
    get the first copy only. Words alternate between solid and outline text.
    $words (list of strings), $speed (seconds per loop), $reverse (scrolls to the right), $tilt.
    A theme can replace it: themes/{theme}/widgets/sections-marquee.blade.php
--}}
<div @class(['marquee-ribbon', 'is-tilted' => $tilt, 'is-reverse' => $reverse]) style="--marquee-speed: {{ $speed }}s">
    <div class="marquee-ribbon-band">
        <div class="marquee-ribbon-track">
            @foreach ([false, true] as $copy)
                <ul class="marquee-ribbon-list"@if ($copy) aria-hidden="true"@endif>
                    @foreach ($words as $word)
                        <li @class(['is-outline' => $loop->even])>{{ $word }}<span class="marquee-ribbon-sep" aria-hidden="true">✳</span></li>
                    @endforeach
                </ul>
            @endforeach
        </div>
    </div>
</div>
