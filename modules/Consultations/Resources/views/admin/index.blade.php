<x-admin-layout::app
    :title="__('consultations::admin.title')"
    :description="__('consultations::admin.description', ['count' => $consultations->total(), 'new' => $unhandled])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('consultations::admin.title') => null]">

    <x-admin.table bulk-form="bulk-form" :empty="__('consultations::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('consultations::admin.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('consultations::admin.fields.status') }}">
                    <option value="">{{ __('consultations::admin.all_statuses') }}</option>
                    @foreach (__('consultations::admin.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.consultations.bulk')" :actions="__('consultations::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('consultations::admin.fields.name') }}</th>
            <th>{{ __('consultations::admin.fields.service') }}</th>
            <th>{{ __('consultations::admin.fields.message') }}</th>
            <th>{{ __('consultations::admin.fields.status') }}</th>
            <th>{{ __('consultations::admin.fields.created_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($consultations as $consultation)
            <tr>
                <x-admin.table.check :value="$consultation->id" :label="$consultation->name" />
                <td class="col-main" data-label="{{ __('consultations::admin.fields.name') }}">
                    <a href="{{ route('admin.consultations.show', $consultation) }}" @class(['tbl-title', 'fw-bold' => $consultation->isNew()])>{{ $consultation->name }}</a>
                    <span class="tbl-sub">{{ $consultation->email }}</span>
                </td>
                <td data-label="{{ __('consultations::admin.fields.service') }}">{{ $consultation->service ?: '—' }}</td>
                <td data-label="{{ __('consultations::admin.fields.message') }}" class="text-muted">
                    {{ $consultation->message ? \Illuminate\Support\Str::limit($consultation->message, 70) : '—' }}
                </td>
                <td data-label="{{ __('consultations::admin.fields.status') }}">
                    <span @class(['badge-status', 'warning' => $consultation->isNew(), 'success' => ! $consultation->isNew()])>
                        {{ __('consultations::admin.statuses.'.$consultation->status) }}
                    </span>
                </td>
                <td data-label="{{ __('consultations::admin.fields.created_at') }}" class="tbl-date">
                    {{ $consultation->created_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $consultation->created_at?->format('H:i') }}</span>
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        <a href="mailto:{{ $consultation->email }}" class="icon-btn" title="{{ __('consultations::admin.reply') }}" aria-label="{{ __('consultations::admin.reply') }}">
                            <i class="fas fa-reply"></i>
                        </a>
                        <a href="{{ route('admin.consultations.show', $consultation) }}" class="icon-btn" title="{{ __('consultations::admin.open') }}" aria-label="{{ __('consultations::admin.open') }}">
                            <i class="fas fa-eye"></i>
                        </a>
                        @can('delete', $consultation)
                            <x-admin.confirm-delete :action="route('admin.consultations.destroy', $consultation)" icon-only
                                :message="__('consultations::admin.delete_confirm', ['name' => $consultation->name])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$consultations" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
