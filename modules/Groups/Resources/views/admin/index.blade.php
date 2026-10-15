<x-admin-layout::app
    :title="__('groups::admin.title')"
    :description="__('groups::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('groups::admin.title') => null]">

    @can('create', \Modules\Groups\Models\Group::class)
        <x-slot:actions>
            <a href="{{ route('admin.groups.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('groups::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table :empty="__('groups::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('groups::admin.search')" />
        </x-slot:toolbar>

        <x-slot:head>
            <th>{{ __('groups::admin.fields.title') }}</th>
            <th>{{ __('groups::admin.fields.name') }}</th>
            <th>{{ __('groups::admin.fields.users') }}</th>
            <th>{{ __('groups::admin.fields.permissions') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($groups as $group)
            <tr>
                <td class="col-main" data-label="{{ __('groups::admin.fields.title') }}">
                    <div class="tbl-item">
                        <div class="sum-ico"><i class="fas fa-users-cog"></i></div>
                        <div>
                            @can('update', $group)
                                <a href="{{ route('admin.groups.edit', $group) }}" class="tbl-title">{{ $group->label() }}</a>
                            @else
                                <span class="tbl-title">{{ $group->label() }}</span>
                            @endcan
                            @if ($group->isProtected())
                                <span class="tbl-sub"><i class="fas fa-lock me-1"></i>{{ __('groups::admin.protected') }}</span>
                            @endif
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('groups::admin.fields.name') }}"><span class="code-tag">{{ $group->name }}</span></td>
                <td data-label="{{ __('groups::admin.fields.users') }}"><span class="tbl-num">{{ $group->users_count }}</span></td>
                <td data-label="{{ __('groups::admin.fields.permissions') }}">
                    @if ($group->isSuperAdmin())
                        <span class="badge-soft success">{{ __('groups::admin.all_permissions') }}</span>
                    @else
                        <span class="tbl-num">{{ $group->permissions_count }}</span>
                    @endif
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        @can('update', $group)
                            <a href="{{ route('admin.groups.edit', $group) }}" class="icon-btn" title="{{ __('groups::admin.edit') }}" aria-label="{{ __('groups::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @if (! $group->isProtected() && auth()->user()->can('delete', $group))
                            <x-admin.confirm-delete :action="route('admin.groups.destroy', $group)" icon-only
                                :text="__('groups::admin.delete_text', ['count' => $group->users_count])" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$groups" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
