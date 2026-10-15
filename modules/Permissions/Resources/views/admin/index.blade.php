<x-admin-layout::app
    :title="__('permissions::admin.title')"
    :description="__('permissions::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('permissions::admin.title') => null]">

    <form method="POST" action="{{ route('admin.permissions.update') }}">
        @csrf
        @method('PUT')

        <div class="card card-auto">
            <div class="table-responsive">
                <table class="table table-custom table-padded align-middle permission-matrix">
                    <thead>
                        <tr>
                            <th>{{ __('permissions::admin.permission') }}</th>
                            @foreach ($groups as $group)
                                <th class="text-center">
                                    {{ $group->label() }}
                                    @if ($group->isSuperAdmin())
                                        <span class="tbl-sub" style="text-transform:none;letter-spacing:0;">{{ __('permissions::admin.all_permissions') }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sections as $section)
                            <tr>
                                <th colspan="{{ $groups->count() + 1 }}" class="pt-4 pb-2"
                                    style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--primary);background:transparent;">
                                    {{ $section['label'] }}
                                </th>
                            </tr>
                            @foreach ($section['permissions'] as $permission)
                                <tr>
                                    <td>
                                        <span style="color:var(--text-dark);font-weight:600;">{{ $permission['label'] }}</span>
                                        <span class="tbl-sub"><code>{{ $permission['name'] }}</code></span>
                                    </td>
                                    @foreach ($groups as $group)
                                        @php
                                            $id = 'perm-'.$group->id.'-'.\Illuminate\Support\Str::slug($permission['name']);
                                            $checked = $group->isSuperAdmin() || in_array($permission['name'], $granted[$group->id], true);
                                        @endphp
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input check-soft" id="{{ $id }}"
                                                @if (! $group->isSuperAdmin()) name="matrix[{{ $group->id }}][]" @endif
                                                value="{{ $permission['name'] }}" @checked($checked)
                                                @disabled($group->isSuperAdmin() || ! $canEdit)
                                                aria-label="{{ $group->label() }}: {{ $permission['label'] }}">
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @empty
                            <tr class="table-empty">
                                <td colspan="{{ $groups->count() + 1 }}"><i class="fas fa-shield-alt"></i>{{ __('permissions::admin.empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($canEdit)
                <div class="table-footer">
                    <div class="table-footer-start">
                        <button type="submit" class="btn-brand"><i class="fas fa-check"></i> {{ __('permissions::admin.save') }}</button>
                        <span class="table-info">{{ __('permissions::admin.save_hint') }}</span>
                    </div>
                </div>
            @endif
        </div>
    </form>
</x-admin-layout::app>
