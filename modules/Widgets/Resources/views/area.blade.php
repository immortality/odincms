{{-- Default markup of a widget area. $widgets ([instance, key, title, html]), $slug --}}
<div class="widget-area widget-area-{{ $slug }}">
    @foreach ($widgets as $widget)
        <section class="widget widget-{{ str_replace('.', '-', $widget['key']) }} mb-4">
            @if ($widget['title'])
                <h2 class="widget-title h5">{{ $widget['title'] }}</h2>
            @endif
            <div class="widget-body">{!! $widget['html'] !!}</div>
        </section>
    @endforeach
</div>
