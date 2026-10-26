{{-- The page tree as a tree table (odinadmin/pages-list.html, odinadmin/js/tree-table.js). Search, the status
     filter and expand / collapse run in the browser; "Save order" posts to data-reorder (resources/js/admin.js). --}}
@php
    $canEdit = auth()->user()->can('pages.edit');
    $statuses = __('pages::admin.statuses');
@endphp

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

    @if ($rows === [])
        <div class="card card-auto">
            <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;">
                <i class="far fa-folder-open d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
                {{ __('pages::admin.empty') }}
            </div>
        </div>
    @else
        <div class="card card-auto" data-tree-table data-tree-labels="{{ json_encode(__('pages::admin.tree')) }}"
             @if ($canEdit) data-reorder="{{ route('admin.pages.reorder') }}" data-reorder-error="{{ __('pages::admin.order_error') }}" @endif>
            <div class="table-toolbar">
                <div class="tt-filters">
                    <div class="input-icon tt-search">
                        <i class="fas fa-search"></i>
                        <input type="search" class="form-control form-soft" placeholder="{{ __('pages::admin.search') }}"
                               aria-label="{{ __('pages::admin.search') }}" data-tree-search>
                    </div>
                    <select class="form-select form-soft tt-select" aria-label="{{ __('pages::admin.fields.status') }}" data-tree-filter="status">
                        <option value="">{{ __('pages::admin.all_statuses') }}</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="tt-actions">
                    <button type="button" class="btn-soft" data-tree-expand><i class="fas fa-angle-double-down"></i> {{ __('pages::admin.expand_all') }}</button>
                    <button type="button" class="btn-soft" data-tree-collapse><i class="fas fa-angle-double-up"></i> {{ __('pages::admin.collapse_all') }}</button>
                </div>
            </div>

            @if ($canEdit)
                {{-- Shown after rows were dragged --}}
                <div class="tree-order-bar" data-tree-order-bar hidden>
                    <i class="fas fa-info-circle"></i> {{ __('pages::admin.order_changed') }}
                    <div class="btn-row">
                        <button type="button" class="btn-soft btn-sm" data-tree-undo><i class="fas fa-undo"></i> {{ __('pages::admin.undo') }}</button>
                        <button type="button" class="btn-brand btn-sm" data-tree-save><i class="fas fa-save"></i> {{ __('pages::admin.save_order') }}</button>
                    </div>
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-custom table-padded table-scroll align-middle">
                    <thead>
                        <tr>
                            @if ($canEdit)
                                <th class="col-handle"><span class="visually-hidden">{{ __('pages::admin.columns.order') }}</span></th>
                            @endif
                            <th style="width:70px;">{{ __('pages::admin.columns.id') }}</th>
                            <th>{{ __('pages::admin.columns.title') }}</th>
                            <th>{{ __('pages::admin.columns.layout') }}</th>
                            <th>{{ __('pages::admin.columns.status') }}</th>
                            <th>{{ __('pages::admin.columns.updated') }}</th>
                            <th class="col-actions">{{ __('pages::admin.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @include('pages::admin.row', ['page' => $row['page'], 'depth' => $row['depth'], 'children' => $row['children'], 'canEdit' => $canEdit])
                        @endforeach
                        <tr class="table-empty" data-tree-empty hidden>
                            <td colspan="{{ $canEdit ? 7 : 6 }}"><i class="far fa-folder-open"></i>{{ __('pages::admin.not_found') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="table-info" data-tree-info></span>
            </div>
        </div>
    @endif

    @push('admin-scripts')
        <script src="{{ asset('odinadmin/js/tree-table.js') }}?ver={{ config('cms.odinadmin_version') }}"></script>
    @endpush
</x-admin-layout::app>
