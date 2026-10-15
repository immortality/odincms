{{-- A page's title, address, status and actions (a search result, inside a .drag-item). $page --}}
<div class="drag-item-body">
    <span class="drag-item-title">
        @can('pages.edit')
            <a href="{{ route('admin.pages.edit', $page) }}" class="text-reset text-decoration-none">{{ $page->label() }}</a>
        @else
            {{ $page->label() }}
        @endcan
        @unless ($page->isPublished())
            <span class="badge-soft warning ms-1">{{ __('pages::admin.statuses.'.$page->status) }}</span>
        @endunless
        @if ($page->layout !== \Modules\Pages\Models\Page::DEFAULT_LAYOUT)
            <span class="badge-soft muted ms-1">{{ \Modules\Pages\Models\Page::layouts()[$page->layout] ?? $page->layout }}</span>
        @endif
    </span>
    <span class="drag-item-sub">/{{ $page->path }}</span>
</div>
<div class="tbl-actions">
    <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="icon-btn" title="{{ __('pages::admin.view') }}" aria-label="{{ __('pages::admin.view') }}">
        <i class="far fa-eye"></i>
    </a>
    @can('pages.create')
        <a href="{{ route('admin.pages.create', ['parent' => $page->id]) }}" class="icon-btn" title="{{ __('pages::admin.add_child') }}" aria-label="{{ __('pages::admin.add_child') }}">
            <i class="fas fa-plus"></i>
        </a>
    @endcan
    @can('pages.edit')
        <a href="{{ route('admin.pages.edit', $page) }}" class="icon-btn" title="{{ __('pages::admin.edit') }}" aria-label="{{ __('pages::admin.edit') }}">
            <i class="fas fa-pen"></i>
        </a>
    @endcan
    @can('pages.delete')
        <x-admin.confirm-delete :action="route('admin.pages.destroy', $page)" icon-only
            :message="__('pages::admin.delete_confirm', ['title' => $page->label()])" />
    @endcan
</div>
