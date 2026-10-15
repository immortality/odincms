<x-admin-layout::app
    :title="__('services::admin.title')"
    :description="__('services::admin.description', ['count' => $items->total()])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('services::admin.title') => null]">

    @can('create', \Modules\Services\Models\Service::class)
        <x-slot:actions>
            <a href="{{ route('admin.services.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('services::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table bulk-form="bulk-form" :empty="__('services::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('services::admin.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('services::admin.fields.status') }}">
                    <option value="">{{ __('services::admin.all_statuses') }}</option>
                    @foreach (__('services::admin.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.services.bulk')" :actions="__('services::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('services::admin.fields.title') }}</th>
            <th>{{ __('services::admin.fields.status') }}</th>
            <th>{{ __('services::admin.fields.created_at') }}</th>
            <th>{{ __('services::admin.fields.updated_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($items as $item)
            <tr>
                <x-admin.table.check :value="$item->id" :label="$item->title" />
                <td class="col-main" data-label="{{ __('services::admin.fields.title') }}">
                    <div class="tbl-item">
                        @if ($photo = $item->photoUrl())
                            <img src="{{ $photo }}" alt="" class="tbl-thumb" loading="lazy">
                        @else
                            <div class="tbl-thumb d-grid" style="place-items:center;color:var(--text-muted);"><i class="fas fa-concierge-bell"></i></div>
                        @endif
                        <div>
                            @can('update', $item)
                                <a href="{{ route('admin.services.edit', $item) }}" class="tbl-title">{{ $item->title }}</a>
                            @else
                                <span class="tbl-title">{{ $item->title }}</span>
                            @endcan
                            <span class="tbl-sub">/services/{{ $item->slug }}</span>
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('services::admin.fields.status') }}">
                    <span @class(['badge-status', 'success' => $item->isPublished(), 'muted' => ! $item->isPublished()])>
                        {{ __('services::admin.statuses.'.$item->status) }}
                    </span>
                </td>
                <td data-label="{{ __('services::admin.fields.created_at') }}" class="tbl-date">
                    {{ $item->created_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $item->created_at?->format('H:i') }}</span>
                </td>
                <td data-label="{{ __('services::admin.fields.updated_at') }}" class="tbl-date">
                    {{ $item->updated_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $item->updated_at?->format('H:i') }}</span>
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="icon-btn" title="{{ __('services::admin.view') }}" aria-label="{{ __('services::admin.view') }}">
                            <i class="far fa-eye"></i>
                        </a>
                        @can('update', $item)
                            <a href="{{ route('admin.services.edit', $item) }}" class="icon-btn" title="{{ __('services::admin.edit') }}" aria-label="{{ __('services::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('delete', $item)
                            <x-admin.confirm-delete :action="route('admin.services.destroy', $item)" icon-only
                                :message="__('services::admin.delete_confirm', ['title' => $item->title])" />
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
