{{--
    The "home" widget area: each widget is a full-width section in the admin's order, with the widget's
    title as the section heading. Widgets with their own heading (Page, Services: eyebrow + slogan +
    intro, Widget::$ownHeading) show the title themselves.
    Every other section has the light background. $widgets, $slug
--}}
<div class="home-sections">
    @foreach ($widgets as $widget)
        <section @class(['section home-section', 'widget-'.str_replace('.', '-', $widget['key']), 'is-alt' => $loop->even])>
            <div class="container">
                @if ($widget['title'] && ! $widget['own_heading'])
                    <div class="section-head reveal">
                        <h2 class="section-title">{{ $widget['title'] }}</h2>
                    </div>
                @endif
                {!! $widget['html'] !!}
            </div>
        </section>
    @endforeach
</div>
