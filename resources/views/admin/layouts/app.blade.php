{{--
    Admin pages in the OdinAdmin look (reference: odinadmin/index.html): sidebar, top bar, page header,
    flash messages, content, footer.

    <x-admin-layout::app :title="__('Users')" :description="…" :breadcrumbs="['Users' => route(…), 'Edit' => null]">
        <x-slot:actions><a href="…" class="btn-brand"><i class="fas fa-plus"></i> {{ __('Add') }}</a></x-slot:actions>
        …
    </x-admin-layout::app>

    Page scripts and styles: @push('admin-scripts') / @push('admin-styles').
    Third-party libraries the OdinAdmin scripts use (Tom Select, flatpickr…): @push('admin-vendor-styles')
    and @push('admin-vendor') inside @once, so they load once and before js/forms.js.
--}}
@props([
    'title' => null,
    'description' => null,
    'breadcrumbs' => [],
])

@php
    $version = config('cms.odinadmin_version');
    $odin = fn (string $path) => asset('odinadmin/'.$path).'?ver='.$version;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <title>{{ $title ? $title.' | ' : '' }}{{ config('app.name') }} Admin</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.css">
    @stack('admin-vendor-styles')
    <link rel="stylesheet" href="{{ $odin('css/style.css') }}">
    @vite('resources/css/admin.scss')
    @stack('admin-styles')
</head>

<body>
    <div class="app">
        @include('admin.partials.sidebar')

        <div class="main-col">
            @include('admin.partials.topbar')

            <main class="content">
                @if ($title || isset($actions) || $breadcrumbs)
                    <div class="page-header">
                        <div>
                            @if ($breadcrumbs)
                                {{-- odinadmin/breadcrumbs.html, arrow separators; spacing from .page-header .breadcrumb --}}
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb breadcrumb-arrow">
                                        @foreach ($breadcrumbs as $label => $url)
                                            @if ($url && ! $loop->last)
                                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                                            @else
                                                <li class="breadcrumb-item active" aria-current="page">{{ $label }}</li>
                                            @endif
                                        @endforeach
                                    </ol>
                                </nav>
                            @endif
                            @if ($title)
                                <h1 class="page-title">{{ $title }}</h1>
                            @endif
                            @if ($description)
                                <p class="page-sub">{{ $description }}</p>
                            @endif
                        </div>
                        @isset($actions)
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                {{ $actions }}
                            </div>
                        @endisset
                    </div>
                @endif

                @include('admin.partials.flash')

                {{ $slot }}
            </main>

            @include('admin.partials.footer')
        </div>
    </div>

    <script>
        window.OdinCMS = { locale: @js(app()->getLocale()) };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.26.25/dist/sweetalert2.min.js"></script>
    @stack('admin-vendor')
    <script src="{{ $odin('js/app.js') }}"></script>
    <script src="{{ $odin('js/alerts.js') }}"></script>
    <script src="{{ $odin('js/forms.js') }}"></script>
    @vite('resources/js/admin.js')
    @stack('admin-scripts')
</body>

</html>
