<x-admin-layout::app
    :title="__('pages::admin.title')"
    :description="__('pages::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('pages::admin.title') => null]">

    @can('pages.create')
        <x-slot:actions>
            <a href="{{ route('admin.pages.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('pages::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <div class="card card-auto mb-4">
        <div class="table-toolbar border-0">
            <x-admin.filters :action="route('admin.pages.index')" :placeholder="__('pages::admin.search')" />
        </div>
    </div>

    @if ($results !== null)
        <div class="card card-auto">
            <div class="card-body">
                @forelse ($results as $page)
                    <div @class(['drag-item has-handle', 'mt-2' => ! $loop->first])>
                        @include('pages::admin.row', ['page' => $page])
                    </div>
                @empty
                    <div class="text-center py-4" style="color:var(--text-muted);font-size:.85rem;">
                        <i class="fas fa-search d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
                        {{ __('pages::admin.not_found') }}
                    </div>
                @endforelse
            </div>
        </div>
    @elseif ($tree === [])
        <div class="card card-auto">
            <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;">
                <i class="far fa-folder-open d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
                {{ __('pages::admin.empty') }}
            </div>
        </div>
    @else
        {{-- Left: the jsTree; right: the selected page. See resources/js/admin/page-tree.js. --}}
        <div class="row g-4" data-page-tree-wrap>
            <div class="col-lg-5">
                <div class="card card-auto">
                    <div class="card-body">
                        <div data-page-tree
                             @can('pages.edit') data-reorder="{{ route('admin.pages.reorder') }}" @endcan
                             data-max-depth="{{ \Modules\Pages\Http\Controllers\Admin\PageController::MAX_DEPTH }}"></div>
                        <script type="application/json" data-page-tree-data>@json($tree)</script>
                    </div>
                    @can('pages.edit')
                        <div class="table-footer">
                            <span class="table-info"><i class="fas fa-info-circle me-1"></i> {{ __('pages::admin.tree_hint') }}</span>
                        </div>
                    @endcan
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card card-auto page-tree-panel" data-page-panel>
                    <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;" data-panel-empty>
                        <i class="far fa-file-alt d-block mb-2" style="font-size:2rem;color:var(--border-strong);"></i>
                        {{ __('pages::admin.select_hint') }}
                    </div>

                    <div class="card-body" data-panel-page hidden>
                        <img src="" alt="" class="page-tree-panel-image mb-3" data-panel-image hidden>

                        <h2 class="page-title mb-3" style="font-size:1.15rem;">
                            <span data-panel-title></span>
                            <span class="badge-soft warning ms-1 align-middle" data-panel-status hidden></span>
                        </h2>

                        <div class="mb-4">
                            <div class="sum-row">
                                <div class="sum-ico"><i class="fas fa-link"></i></div>
                                <span class="sum-label">{{ __('pages::admin.fields.slug') }}</span>
                                <span class="code-tag" data-panel-slug></span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="fas fa-globe"></i></div>
                                <span class="sum-label">{{ __('pages::admin.fields.address') }}</span>
                                <a href="#" target="_blank" rel="noopener" class="text-decoration-none" data-panel-url>
                                    <span data-panel-path></span> <i class="fas fa-external-link-alt" style="font-size:.7rem;"></i>
                                </a>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-object-group"></i></div>
                                <span class="sum-label">{{ __('pages::admin.fields.layout') }}</span>
                                <span class="sum-val" data-panel-layout></span>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            @can('pages.edit')
                                <a href="#" class="btn-brand" data-panel-edit>
                                    <i class="fas fa-pen"></i> {{ __('Edit') }}
                                </a>
                            @endcan
                            @can('pages.delete')
                                {{-- The action and the message are set for the selected page. --}}
                                <div data-panel-delete>
                                    <x-admin.confirm-delete action="#" />
                                </div>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-admin-layout::app>
