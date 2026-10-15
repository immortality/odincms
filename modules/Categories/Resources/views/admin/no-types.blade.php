<x-admin-layout::app
    :title="__('categories::admin.title')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('categories::admin.title') => null]">

    <div class="card card-auto">
        <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;">
            <i class="fas fa-folder-tree d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
            {{ __('categories::admin.no_types') }}
        </div>
    </div>
</x-admin-layout::app>
