{{-- The "Hero banner" widget: one banner. $banner. A theme can replace it: themes/{theme}/widgets/banners-hero.blade.php --}}
<section class="hero">
    <div class="container">
        @include('banners::widgets.partials.content', ['banner' => $banner, 'animate' => true])
    </div>
</section>
