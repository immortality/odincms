{{--
    A drag-and-drop tree in a card (menu links, categories). The slot holds nested
    <ul class="cms-tree"><li class="cms-tree-item" data-id="…"><div class="cms-tree-row drag-item has-handle">…</div> <ul class="cms-tree">…</ul></li></ul>
    with a .sortable-handle (.drag-handle) in each row. With an action (the reorder URL), the order can be saved;
    leave it null for users who may not reorder. See initTrees() in resources/js/admin.js; styles in resources/css/admin.scss.

    <x-admin.tree :action="$canEdit ? route('admin.categories.reorder', $type) : null" :empty="$categories->isEmpty()">
        @include('categories::admin.tree', ['parentId' => 0])
    </x-admin.tree>
--}}
@props([
    'action' => null,
    'empty' => false,
    'emptyText' => __('Nothing here yet.'),
    'hint' => __('Drag the items to reorder or nest them, then save the order.'),
])

<div {{ $attributes->class(['card card-auto']) }} @if ($action) data-tree="{{ $action }}" @endif>
    @if ($empty)
        <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;">
            <i class="far fa-folder-open d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
            {{ $emptyText }}
        </div>
    @else
        <div class="card-body">
            {{ $slot }}
        </div>

        @if ($action)
            <div class="table-footer">
                <div class="table-footer-start">
                    <button type="button" class="btn-brand" data-tree-save disabled><i class="fas fa-check"></i> {{ __('Save order') }}</button>
                    <span class="table-info">{{ $hint }}</span>
                </div>
            </div>
        @endif
    @endif
</div>
