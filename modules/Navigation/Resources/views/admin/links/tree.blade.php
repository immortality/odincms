{{-- One level of the link tree. $tree (links by parent id), $parentId, $group, $renderer --}}
<ul class="cms-tree">
    @foreach ($tree->get($parentId, collect()) as $link)
        @php($url = $renderer->url($link))
        <li class="cms-tree-item" data-id="{{ $link->id }}">
            <div class="cms-tree-row drag-item has-handle">
                @can('navigation.edit')
                    <span class="drag-handle sortable-handle" title="{{ __('navigation::admin.drag') }}"><i class="fas fa-grip-vertical"></i></span>
                @endcan
                <div class="drag-item-body">
                    <span class="drag-item-title">
                        {{ $link->getTranslation('title', app()->getLocale(), true) ?: ($link->linkable?->linkLabel() ?? '—') }}
                        <span class="badge-soft muted">{{ __('navigation::admin.types.'.$link->type) }}</span>
                        @unless ($link->is_active)
                            <span class="badge-soft warning">{{ __('navigation::admin.inactive') }}</span>
                        @endunless
                        @if ($link->visibility)
                            <span class="badge-soft info" title="{{ implode(', ', $link->visibility) }}"><i class="far fa-eye"></i></span>
                        @endif
                    </span>
                    <span class="drag-item-sub" @if ($url === null) style="color:var(--danger);" @endif>
                        {{ $url ?? __('navigation::admin.broken') }}
                    </span>
                </div>
                <div class="tbl-actions">
                    @can('navigation.create')
                        <a href="{{ route('admin.navigation.links.create', [$group, 'parent' => $link->id]) }}" class="icon-btn"
                            title="{{ __('navigation::admin.add_child') }}" aria-label="{{ __('navigation::admin.add_child') }}"><i class="fas fa-plus"></i></a>
                    @endcan
                    @can('navigation.edit')
                        <a href="{{ route('admin.navigation.links.edit', [$group, $link]) }}" class="icon-btn"
                            title="{{ __('navigation::admin.edit_link') }}" aria-label="{{ __('navigation::admin.edit_link') }}"><i class="fas fa-pen"></i></a>
                    @endcan
                    @can('navigation.delete')
                        <x-admin.confirm-delete :action="route('admin.navigation.links.destroy', [$group, $link])" icon-only :text="__('navigation::admin.delete_link_text')" />
                    @endcan
                </div>
            </div>
            @include('navigation::admin.links.tree', ['parentId' => $link->id])
        </li>
    @endforeach
</ul>
