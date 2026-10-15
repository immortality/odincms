<x-admin-layout::app
    :title="$group->title"
    :description="__('navigation::admin.links_description', ['code' => '@navigation(\''.$group->slug.'\')'])"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('navigation::admin.title') => route('admin.navigation.index'),
        $group->title => null,
    ]">

    @can('navigation.create')
        <x-slot:actions>
            <a href="{{ route('admin.navigation.links.create', $group) }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('navigation::admin.create_link') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.tree :action="auth()->user()->can('navigation.edit') ? route('admin.navigation.links.reorder', $group) : null"
        :empty="$tree->isEmpty()" :empty-text="__('navigation::admin.no_links')" :hint="__('navigation::admin.drag_hint')">
        @include('navigation::admin.links.tree', ['parentId' => 0])
    </x-admin.tree>
</x-admin-layout::app>
