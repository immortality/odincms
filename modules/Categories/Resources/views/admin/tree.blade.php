{{-- One level of the category tree. $tree (categories by parent id), $parentId, $type, $types (type => module label) --}}
<ul class="cms-tree">
    @foreach ($tree->get($parentId, collect()) as $category)
        <li class="cms-tree-item" data-id="{{ $category->id }}">
            <div class="cms-tree-row drag-item has-handle">
                @can('categories.edit')
                    <span class="drag-handle sortable-handle" title="{{ __('categories::admin.drag') }}"><i class="fas fa-grip-vertical"></i></span>
                @endcan
                <div class="drag-item-body">
                    <span class="drag-item-title">
                        @can('categories.edit')
                            <a href="{{ route('admin.categories.edit', [$type, $category]) }}" class="text-reset text-decoration-none">{{ $category->label() }}</a>
                        @else
                            {{ $category->label() }}
                        @endcan
                        <span class="badge-soft muted" title="{{ __('categories::admin.module') }}">{{ $types[$category->type] ?? $category->type }}</span>
                        @unless ($category->is_active)
                            <span class="badge-soft warning">{{ __('categories::admin.inactive') }}</span>
                        @endunless
                    </span>
                    <span class="drag-item-sub">{{ $category->slug }}</span>
                </div>
                <div class="tbl-actions">
                    @can('categories.create')
                        <a href="{{ route('admin.categories.create', [$type, 'parent' => $category->id]) }}" class="icon-btn"
                            title="{{ __('categories::admin.add_child') }}" aria-label="{{ __('categories::admin.add_child') }}"><i class="fas fa-plus"></i></a>
                    @endcan
                    @can('categories.edit')
                        <a href="{{ route('admin.categories.edit', [$type, $category]) }}" class="icon-btn"
                            title="{{ __('categories::admin.edit') }}" aria-label="{{ __('categories::admin.edit') }}"><i class="fas fa-pen"></i></a>
                    @endcan
                    @can('categories.delete')
                        <x-admin.confirm-delete :action="route('admin.categories.destroy', [$type, $category])" icon-only
                            :message="__('categories::admin.delete_confirm', ['title' => $category->label()])" />
                    @endcan
                </div>
            </div>
            @include('categories::admin.tree', ['parentId' => $category->id])
        </li>
    @endforeach
</ul>
