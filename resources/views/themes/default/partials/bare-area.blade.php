{{-- A widget area without wrappers or titles: each widget's HTML as is (e.g. the full-width hero). $widgets, $slug --}}
@foreach ($widgets as $widget)
    {!! $widget['html'] !!}
@endforeach
