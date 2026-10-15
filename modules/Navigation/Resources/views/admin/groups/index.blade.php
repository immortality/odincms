<x-admin-layout::app
    :title="__('navigation::admin.title')"
    :description="__('navigation::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('navigation::admin.title') => null]">

    @can('navigation.create')
        <x-slot:actions>
            <a href="{{ route('admin.navigation.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('navigation::admin.create_group') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table :empty="__('navigation::admin.no_groups')">
        <x-slot:head>
            <th>{{ __('navigation::admin.fields.title') }}</th>
            <th>{{ __('navigation::admin.fields.usage') }}</th>
            <th>{{ __('navigation::admin.fields.links') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($groups as $group)
            <tr>
                <td class="col-main" data-label="{{ __('navigation::admin.fields.title') }}">
                    <div class="tbl-item">
                        <div class="sum-ico"><i class="fas fa-compass"></i></div>
                        <a href="{{ route('admin.navigation.links.index', $group) }}" class="tbl-title">{{ $group->title }}</a>
                    </div>
                </td>
                <td data-label="{{ __('navigation::admin.fields.usage') }}"><span class="code-tag">@@navigation('{{ $group->slug }}')</span></td>
                <td data-label="{{ __('navigation::admin.fields.links') }}"><span class="tbl-num">{{ $group->links_count }}</span></td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        <a href="{{ route('admin.navigation.links.index', $group) }}" class="icon-btn" title="{{ __('navigation::admin.edit_links') }}" aria-label="{{ __('navigation::admin.edit_links') }}">
                            <i class="fas fa-list-ul"></i>
                        </a>
                        @can('navigation.edit')
                            <a href="{{ route('admin.navigation.edit', $group) }}" class="icon-btn" title="{{ __('navigation::admin.edit_group') }}" aria-label="{{ __('navigation::admin.edit_group') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('navigation.delete')
                            <x-admin.confirm-delete :action="route('admin.navigation.destroy', $group)" icon-only
                                :text="__('navigation::admin.delete_group_text', ['count' => $group->links_count])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout::app>
