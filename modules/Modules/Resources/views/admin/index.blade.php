@php
    $statusBadges = [
        'enabled' => 'success',
        'disabled' => 'warning',
        'not_installed' => 'muted',
        'missing' => 'danger',
    ];
    $actionButtons = [
        'install' => ['icon' => 'fas fa-download', 'class' => 'btn-brand btn-sm'],
        'upgrade' => ['icon' => 'fas fa-arrow-up', 'class' => 'btn-brand btn-sm'],
        'enable' => ['icon' => 'fas fa-play', 'class' => 'btn-soft btn-sm'],
        'disable' => ['icon' => 'fas fa-pause', 'class' => 'btn-soft btn-sm'],
        'uninstall' => ['icon' => 'far fa-trash-alt', 'class' => 'btn-soft btn-sm danger'],
    ];
@endphp

<x-admin-layout::app
    :title="__('modules::admin.title')"
    :description="__('modules::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('modules::admin.title') => null]">

    @if ($canManage)
        <x-slot:actions>
            <form method="POST" action="{{ route('admin.modules.sync') }}">
                @csrf
                <button type="submit" class="btn-soft">
                    <i class="fas fa-sync-alt"></i> {{ __('modules::admin.sync_button') }}
                </button>
            </form>
        </x-slot:actions>
    @endif

    @if ($needsSync || $manifestErrors)
        <div class="mb-4">
            @if ($needsSync)
                <div class="alert-soft info" role="alert">
                    <i class="fas fa-info-circle"></i>
                    <div class="alert-body">{{ __('modules::admin.needs_sync') }}</div>
                </div>
            @endif
            @foreach ($manifestErrors as $error)
                <div class="alert-soft warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div class="alert-body">{{ $error }}</div>
                </div>
            @endforeach
        </div>
    @endif

    <x-admin.table :empty="__('modules::admin.empty')">
        <x-slot:head>
            <th>{{ __('modules::admin.fields.module') }}</th>
            <th>{{ __('modules::admin.fields.version') }}</th>
            <th>{{ __('modules::admin.fields.status') }}</th>
            <th>{{ __('modules::admin.fields.requires') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($modules as $row)
            @php($module = $row['module'])
            <tr id="module-{{ $module->slug }}">
                <td class="col-main" data-label="{{ __('modules::admin.fields.module') }}">
                    <div class="tbl-item">
                        <div class="sum-ico"><i class="{{ \App\Cms\Admin\AdminIcon::fa($row['icon'] ?? null, 'fas fa-cube') }}"></i></div>
                        <div>
                            <span class="tbl-title">
                                {{ $module->name }}
                                @if ($module->is_core)
                                    <span class="badge-soft muted ms-1">{{ __('modules::admin.core') }}</span>
                                @endif
                            </span>
                            <span class="tbl-sub" style="white-space:normal;">{{ $row['description'] }}</span>
                            <span class="code-tag mt-1">{{ $module->slug }}</span>
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('modules::admin.fields.version') }}">
                    @if ($module->isInstalled())
                        <span class="tbl-num">{{ $module->installed_version }}</span>
                        @if ($module->installed_version !== $module->version)
                            <span class="tbl-sub" style="color:var(--info);">{{ __('modules::admin.available', ['version' => $module->version]) }}</span>
                        @endif
                    @else
                        <span style="color:var(--text-muted);">{{ $module->version }}</span>
                    @endif
                </td>
                <td data-label="{{ __('modules::admin.fields.status') }}">
                    <span class="badge-status {{ $statusBadges[$row['status']] }}">{{ __('modules::admin.statuses.'.$row['status']) }}</span>
                </td>
                <td data-label="{{ __('modules::admin.fields.requires') }}">
                    @forelse ($row['requires'] as $required)
                        <span @class([
                            'badge-outline',
                            'success' => ($statuses[$required] ?? null) === 'enabled',
                            'danger' => ($statuses[$required] ?? null) !== 'enabled',
                        ]) title="{{ __('modules::admin.statuses.'.($statuses[$required] ?? 'missing')) }}">{{ $required }}</span>
                    @empty
                        <span style="color:var(--text-muted);">—</span>
                    @endforelse
                    @if ($row['requiredBy'] !== [])
                        <span class="tbl-sub" style="white-space:normal;">{{ __('modules::admin.required_by', ['modules' => implode(', ', $row['requiredBy'])]) }}</span>
                    @endif
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="d-inline-flex flex-wrap justify-content-end gap-2">
                        @foreach ($row['actions'] as $action)
                            <form method="POST" action="{{ route('admin.modules.action', [$module->slug, $action]) }}"
                                @if ($action === 'uninstall')
                                    data-confirm="{{ __('modules::admin.uninstall_confirm', ['name' => $module->name]) }}"
                                    data-confirm-text="{{ __('modules::admin.uninstall_text') }}"
                                    data-confirm-button="{{ __('modules::admin.actions.uninstall') }}"
                                @endif>
                                @csrf
                                <button type="submit" class="{{ $actionButtons[$action]['class'] }}">
                                    <i class="{{ $actionButtons[$action]['icon'] }}"></i>
                                    <span>{{ __('modules::admin.actions.'.$action) }}</span>
                                </button>
                            </form>
                        @endforeach
                    </div>
                </td>
            </tr>
        @endforeach
    </x-admin.table>
</x-admin-layout::app>
