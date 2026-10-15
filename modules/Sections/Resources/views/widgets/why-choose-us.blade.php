{{--
    The "Why choose us" widget: a decorative illustration (pure CSS, resources/css/theme/_sections.scss) beside
    the heading (eyebrow = the widget title, slogan, intro), up to two cards, a progress bar and a button.
    Every value is plain text. $eyebrow, $slogan, $intro, $badge, $cards (icon, title, text),
    $bar (label, value) or null, $link (label, href) or null.
    The progress fill grows when it scrolls into view (resources/js/theme.js); without JS it is full.
    A theme can replace it: themes/{theme}/widgets/sections-why-choose-us.blade.php
--}}
<div class="why-choose-us on-dark">
    {{-- g-4 on phones: a 3rem gutter is wider than the container padding and would overflow the screen. --}}
    <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-6">
            <div class="wcu-art reveal" aria-hidden="true">
                <div class="wcu-art-panel">
                    <div class="wcu-art-grid">
                        @for ($i = 0; $i < 15; $i++)
                            <span @class(['wcu-shape', 'is-circle' => $i % 2 === 1])></span>
                        @endfor
                    </div>
                </div>
                @if ($badge !== '')
                    <span class="wcu-badge"><i class="bi bi-check-circle-fill"></i> {{ $badge }}</span>
                @endif
                <div class="wcu-art-card">
                    <span class="wcu-ring"></span>
                    <span class="wcu-dot"></span>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <x-theme::section-head :eyebrow="$eyebrow" :slogan="$slogan" :intro="$intro" align="start" />

            @if ($cards !== [])
                <div class="wcu-cards reveal">
                    @foreach ($cards as $card)
                        <div class="wcu-card">
                            <div class="wcu-card-icon"><i class="bi bi-{{ $card['icon'] }}"></i></div>
                            <div>
                                <h3 class="wcu-card-title">{{ $card['title'] }}</h3>
                                @if ($card['text'] !== '')
                                    <p class="wcu-card-text">{{ $card['text'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($bar)
                <div class="wcu-progress reveal">
                    <div class="wcu-progress-label">
                        <span>{{ $bar['label'] }}</span>
                        <span>{{ $bar['value'] }}%</span>
                    </div>
                    <div class="wcu-progress-track" role="progressbar" aria-label="{{ $bar['label'] }}"
                         aria-valuenow="{{ $bar['value'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="wcu-progress-fill" style="--wcu-value: {{ $bar['value'] }}%" data-progress="{{ $bar['value'] }}"></div>
                    </div>
                </div>
            @endif

            @if ($link)
                <div class="wcu-action reveal">
                    <a href="{{ $link['href'] }}" class="btn btn-lime">{{ $link['label'] }} <i class="bi bi-arrow-right"></i></a>
                </div>
            @endif
        </div>
    </div>
</div>
