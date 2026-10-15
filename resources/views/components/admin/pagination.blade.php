{{-- "Showing x–y of n" and the page buttons, for <x-admin.table>'s footer. <x-admin.pagination :paginator="$posts" /> --}}
@props([
    'paginator',
])

@if (method_exists($paginator, 'total') && $paginator->total() > 0)
    <span class="table-info">
        {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
    </span>
@endif

{{ $paginator->onEachSide(1)->links('admin.partials.pagination') }}
