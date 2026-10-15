<x-admin-layout::app
    :title="__('users::admin.title')"
    :description="__('users::admin.description', ['count' => $users->total()])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('users::admin.title') => null]">

    @can('create', \App\Models\User::class)
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn-brand">
                <i class="fas fa-user-plus"></i> {{ __('users::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table bulk-form="bulk-form" :empty="__('users::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('users::admin.search')">
                <select name="group" class="form-select form-soft tt-select" aria-label="{{ __('users::admin.fields.groups') }}">
                    <option value="">{{ __('users::admin.all_groups') }}</option>
                    @foreach ($groups as $value => $label)
                        <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('users::admin.fields.status') }}">
                    <option value="">{{ __('users::admin.all_statuses') }}</option>
                    @foreach (__('users::admin.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.users.bulk')" :actions="__('users::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('users::admin.fields.name') }}</th>
            <th>{{ __('users::admin.fields.groups') }}</th>
            <th>{{ __('users::admin.fields.status') }}</th>
            <th>{{ __('users::admin.fields.last_login_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($users as $user)
            <tr>
                <x-admin.table.check :value="$user->id" :label="$user->name" />
                <td class="col-main" data-label="{{ __('users::admin.fields.name') }}">
                    <div class="tbl-item">
                        @include('users::admin.partials.avatar', ['user' => $user, 'tone' => 'c'.($loop->index % 5 + 1)])
                        <div>
                            @can('update', $user)
                                <a href="{{ route('admin.users.edit', $user) }}" class="tbl-title">{{ $user->name }}</a>
                            @else
                                <span class="tbl-title">{{ $user->name }}</span>
                            @endcan
                            <span class="tbl-sub">
                                {{ $user->email }}
                                @if ($user->is(auth()->user()))
                                    · <span style="color:var(--primary);font-weight:600;">{{ __('users::admin.you') }}</span>
                                @endif
                            </span>
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('users::admin.fields.groups') }}">
                    @foreach ($user->roles as $role)
                        <span class="badge-soft primary">{{ $groups[$role->name] ?? \Illuminate\Support\Str::headline($role->name) }}</span>
                    @endforeach
                </td>
                <td data-label="{{ __('users::admin.fields.status') }}">
                    <span @class(['badge-status', 'success' => $user->is_active, 'danger' => ! $user->is_active])>
                        {{ __('users::admin.statuses.'.($user->is_active ? 'active' : 'inactive')) }}
                    </span>
                </td>
                <td data-label="{{ __('users::admin.fields.last_login_at') }}" class="tbl-date">
                    {{ $user->last_login_at?->diffForHumans() ?? __('users::admin.never') }}
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        @can('update', $user)
                            <a href="{{ route('admin.users.edit', $user) }}" class="icon-btn" title="{{ __('users::admin.edit') }}" aria-label="{{ __('users::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @if (! $user->is(auth()->user()) && auth()->user()->can('delete', $user))
                            <x-admin.confirm-delete :action="route('admin.users.destroy', $user)" icon-only
                                :message="__('users::admin.delete_confirm', ['name' => $user->name])" />
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$users" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
