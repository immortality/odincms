{{-- Paginator view in OdinAdmin markup (.pager). Used by <x-admin.pagination>. --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="{{ __('Pages') }}">
        @if ($paginator->onFirstPage())
            <span class="pager-btn disabled" aria-disabled="true" aria-label="{{ __('Prev') }}"><i class="fas fa-chevron-left"></i></span>
        @else
            <a class="pager-btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Prev') }}"><i class="fas fa-chevron-left"></i></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="pager-gap">…</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="pager-btn active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="pager-btn" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a class="pager-btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}"><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="pager-btn disabled" aria-disabled="true" aria-label="{{ __('Next') }}"><i class="fas fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
