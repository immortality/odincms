@php
    // Page views set these with @section('title', …), @section('meta_description', …), @section('og_image', …)
    // and @section('og_type', 'article'); a $title view variable works too.
    // An inline @section is stored escaped; decode it so {{ }} below escapes it once.
    $pageTitle = html_entity_decode(trim($__env->yieldContent('title')), ENT_QUOTES | ENT_HTML5) ?: ($title ?? null);
    $locales = (array) config('cms.locales');
    $tagline = \App\Cms\Support\Facades\Settings::translated('site.tagline');

    // Template images (html/assets/img) copied to public/themes/default; see config('cms.theme_version').
    $themeAsset = fn (string $path) => asset('themes/default/'.$path).'?ver='.config('cms.theme_version');

    // The site contact (Contacts module, cached; null when the module is off or has no published contact).
    $siteContact ??= null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ? $pageTitle.' | ' : '' }}{{ $siteName }}</title>

    @hasSection('meta_description')
        <meta name="description" content="@yield('meta_description')">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    @if (count($locales) > 1)
        @foreach (\App\Cms\Support\LocalizedUrls::alternates() as $locale => $href)
            <link rel="alternate" hreflang="{{ $locale }}" href="{{ $href }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ \App\Cms\Support\LocalizedUrls::alternates()[config('cms.default_locale')] ?? url('/') }}">
    @endif
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $pageTitle ?? $siteName }}">
    <meta property="og:type" content="{{ trim($__env->yieldContent('og_type')) ?: 'website' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @hasSection('meta_description')
        <meta property="og:description" content="@yield('meta_description')">
    @endif
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @endif
    <link rel="icon" type="image/svg+xml" href="{{ $themeAsset('img/odinzero-icon.svg') }}">
    @stack('head')

    {{-- One CSS file (Bootstrap, icons, self-hosted fonts, the template, CMS styles) and one deferred JS file. --}}
    @vite(['resources/css/theme.scss', 'resources/js/theme.js'])
</head>

<body class="d-flex flex-column min-vh-100">
    @if ($siteContact)
        <div class="topbar d-none d-lg-block">
            <div class="container d-flex justify-content-between align-items-center">
                <div class="info">
                    @foreach ($siteContact->links() as $link)
                        <x-theme::contact-link :link="$link" />
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    <header class="site-header">
        <div class="container">
            <nav class="navbar navbar-expand-xl p-0 main-nav">
                <a class="brand" href="{{ url('/') }}"><img src="{{ $themeAsset('img/odinzero-logo.svg') }}" alt="{{ $siteName }}"></a>

                <div class="mx-auto d-none d-xl-flex">@navigation('header')</div>

                <div class="header-actions ms-auto ms-xl-0">
                    @if (count($locales) > 1)
                        <nav class="lang-switch" aria-label="{{ __('Language') }}">
                            @foreach ($locales as $locale)
                                <a @class(['active' => $locale === app()->getLocale()])
                                   href="{{ \App\Cms\Support\LocalizedUrls::current($locale) }}" hreflang="{{ $locale }}" lang="{{ $locale }}"
                                   title="{{ config("cms.locale_names.{$locale}", strtoupper($locale)) }}"
                                   @if ($locale === app()->getLocale()) aria-current="true" @endif>{{ strtoupper($locale) }}</a>
                            @endforeach
                        </nav>
                    @endif
                    @can('admin.access')
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-brand d-none d-md-inline-flex">{{ __('Admin panel') }} <i class="bi bi-arrow-right"></i></a>
                    @endcan
                    <button class="nav-toggler d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="{{ __('Menu') }}">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </nav>
        </div>
    </header>

    {{-- Mobile menu --}}
    <div class="offcanvas offcanvas-end" tabindex="-1" id="mobileNav">
        <div class="offcanvas-header">
            <a class="brand" href="{{ url('/') }}"><img src="{{ $themeAsset('img/odinzero-logo.svg') }}" alt="{{ $siteName }}"></a>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column">
            <div class="mb-4">@navigation('header')</div>
            @can('admin.access')
                <a href="{{ route('admin.dashboard') }}" class="btn btn-brand justify-content-center mb-4">{{ __('Admin panel') }} <i class="bi bi-arrow-right"></i></a>
            @endcan
            @if ($siteContact)
                <div class="contact-links mt-auto small">
                    @foreach ($siteContact->links(['email', 'phone']) as $link)
                        <p class="mb-1"><x-theme::contact-link :link="$link" icon-class="me-2" /></p>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Page views bring their own <main>. --}}
    <div id="top" class="site-main flex-grow-1">
        @yield('content')
    </div>

    <footer class="footer">
        <div class="container">
            {{-- Logo and tagline, the site contact, the footer menu (Navigation → "footer"). --}}
            <div class="row g-5">
                <div class="col-lg-5 col-md-12">
                    <a class="brand mb-3" href="{{ url('/') }}"><img src="{{ $themeAsset('img/odinzero-logo-light.svg') }}" alt="{{ $siteName }}"></a>
                    @if ($tagline !== '')
                        <p class="mt-3 footer-tagline">{{ $tagline }}</p>
                    @endif
                </div>
                @if ($siteContact)
                    <div class="col-lg-4 col-md-6">
                        <h5>{{ __('Contacts') }}</h5>
                        <ul class="contact">
                            @foreach ($siteContact->links(['address', 'phone', 'email']) as $link)
                                <li><x-theme::contact-link :link="$link" /></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @php
                    // An empty menu still renders an empty <ul>: show the column only when it has links.
                    $footerMenu = app()->bound('cms.navigation') ? (string) app('cms.navigation')->render('footer') : '';
                @endphp
                @if (str_contains($footerMenu, '<a '))
                    <div class="col-lg-3 col-md-6 footer-menu">
                        <h5>{{ __('Menu') }}</h5>
                        {!! $footerMenu !!}
                    </div>
                @endif
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} {{ $siteName }}</span>
            </div>
        </div>
    </footer>

    <button class="to-top" aria-label="{{ __('Back to top') }}"><i class="bi bi-arrow-up"></i></button>
</body>

</html>
