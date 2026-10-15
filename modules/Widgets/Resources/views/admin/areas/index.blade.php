<x-admin-layout::app
    :title="__('widgets::admin.areas')"
    :description="__('widgets::admin.areas_description', ['theme' => config('cms.theme')])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('widgets::admin.title') => route('admin.widgets.index'), __('widgets::admin.areas') => null]">

    @can('widgets.create')
        <x-slot:actions>
            <a href="{{ route('admin.widgets.areas.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('widgets::admin.create_area') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table :empty="__('widgets::admin.no_areas')">
        <x-slot:head>
            <th>{{ __('widgets::admin.fields.area_name') }}</th>
            <th>{{ __('widgets::admin.fields.usage') }}</th>
            <th>{{ __('widgets::admin.title') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($areas as $area)
            <tr>
                <td class="col-main" data-label="{{ __('widgets::admin.fields.area_name') }}">
                    <div class="tbl-item">
                        <div class="sum-ico"><i class="fas fa-th-large"></i></div>
                        <span class="tbl-title">{{ $area->name }}</span>
                    </div>
                </td>
                <td data-label="{{ __('widgets::admin.fields.usage') }}"><span class="code-tag">@@widgetArea('{{ $area->slug }}')</span></td>
                <td data-label="{{ __('widgets::admin.title') }}"><span class="tbl-num">{{ $area->instances_count }}</span></td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        @can('widgets.edit')
                            <a href="{{ route('admin.widgets.areas.edit', $area) }}" class="icon-btn" title="{{ __('widgets::admin.edit_area') }}" aria-label="{{ __('widgets::admin.edit_area') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('widgets.delete')
                            <x-admin.confirm-delete :action="route('admin.widgets.areas.destroy', $area)" icon-only
                                :text="__('widgets::admin.delete_area_text', ['count' => $area->instances_count])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout::app>
