<x-admin-layout::app
    :title="__('banners::admin.title')"
    :description="__('banners::admin.description', ['count' => $items->total()])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('banners::admin.title') => null]">

    @can('create', \Modules\Banners\Models\Banner::class)
        <x-slot:actions>
            <a href="{{ route('admin.banners.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('banners::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table bulk-form="bulk-form" :empty="__('banners::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('banners::admin.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('banners::admin.fields.status') }}">
                    <option value="">{{ __('banners::admin.all_statuses') }}</option>
                    @foreach (__('banners::admin.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.banners.bulk')" :actions="__('banners::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('banners::admin.fields.title') }}</th>
            <th>{{ __('banners::admin.fields.position') }}</th>
            <th>{{ __('banners::admin.fields.status') }}</th>
            <th>{{ __('banners::admin.fields.updated_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($items as $item)
            <tr>
                <x-admin.table.check :value="$item->id" :label="$item->label()" />
                <td class="col-main" data-label="{{ __('banners::admin.fields.title') }}">
                    <div class="tbl-item">
                        @if ($image = $item->imageUrlFor('thumb'))
                            <img src="{{ $image }}" alt="" class="tbl-thumb" loading="lazy">
                        @else
                            <div class="tbl-thumb d-grid" style="place-items:center;color:var(--text-muted);"><i class="fas fa-images"></i></div>
                        @endif
                        <div>
                            @can('update', $item)
                                <a href="{{ route('admin.banners.edit', $item) }}" class="tbl-title">{{ $item->label() }}</a>
                            @else
                                <span class="tbl-title">{{ $item->label() }}</span>
                            @endcan
                            @if ($item->button_label || $item->button_url)
                                <span class="tbl-sub">{{ $item->button_label }} @if ($item->button_url)→ {{ $item->button_url }}@endif</span>
                            @endif
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('banners::admin.fields.position') }}">{{ $item->position }}</td>
                <td data-label="{{ __('banners::admin.fields.status') }}">
                    <span @class(['badge-status', 'success' => $item->isPublished(), 'muted' => ! $item->isPublished()])>
                        {{ __('banners::admin.statuses.'.$item->status) }}
                    </span>
                </td>
                <td data-label="{{ __('banners::admin.fields.updated_at') }}" class="tbl-date">
                    {{ $item->updated_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $item->updated_at?->format('H:i') }}</span>
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        @can('update', $item)
                            <a href="{{ route('admin.banners.edit', $item) }}" class="icon-btn" title="{{ __('banners::admin.edit') }}" aria-label="{{ __('banners::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('delete', $item)
                            <x-admin.confirm-delete :action="route('admin.banners.destroy', $item)" icon-only
                                :message="__('banners::admin.delete_confirm', ['title' => $item->label()])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$items" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
