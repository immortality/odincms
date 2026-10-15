<x-admin-layout::app
    :title="__('categories::admin.title')"
    :description="__('categories::admin.description', ['type' => $types[$type]])"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('categories::admin.title') => route('admin.categories.index'),
        $types[$type] => null,
    ]">

    @can('categories.create')
        <x-slot:actions>
            <a href="{{ route('admin.categories.create', $type) }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('categories::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    {{-- One page per module that has categories: links styled as the template's tabs --}}
    @if (count($types) > 1)
        <ul class="tabs mb-4">
            @foreach ($types as $key => $label)
                <li>
                    <a @class(['tab-link text-decoration-none', 'active' => $key === $type]) href="{{ route('admin.categories.tree', $key) }}"
                        @if ($key === $type) aria-current="page" @endif>{{ $label }}</a>
                </li>
            @endforeach
        </ul>
    @endif

    <x-admin.tree :action="auth()->user()->can('categories.edit') ? route('admin.categories.reorder', $type) : null"
        :empty="$tree->isEmpty()" :empty-text="__('categories::admin.empty')">
        @include('categories::admin.tree', ['parentId' => 0])
    </x-admin.tree>
</x-admin-layout::app>
