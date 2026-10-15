{{-- A page's title and text, shared by the page layouts. $page, $preview --}}
@if ($preview)
    <div class="alert alert-warning">{{ __('pages::front.draft') }}</div>
@endif

@if ($page->parent)
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('pages::front.home') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ $page->parent->url() }}">{{ $page->parent->label() }}</a></li>
        </ol>
    </nav>
@endif

<h1 class="mb-4">{{ $page->label() }}</h1>

{{-- Purified on save (Page::purifiedAttributes). --}}
<div class="page-body">{!! $page->body !!}</div>
