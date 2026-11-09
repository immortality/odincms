<x-admin-layout::app
    :title="__('contacts::messages.title')"
    :description="__('contacts::messages.description', ['count' => $messages->total(), 'new' => $unhandled])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('contacts::admin.title') => route('admin.contacts.index'), __('contacts::messages.title') => null]">

    <x-slot:actions>
        <a href="{{ route('contacts.index') }}" class="btn-soft" target="_blank" rel="noopener">
            <i class="fas fa-external-link-alt"></i> {{ __('contacts::messages.view_page') }}
        </a>
    </x-slot:actions>

    <x-admin.table bulk-form="bulk-form" :empty="__('contacts::messages.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('contacts::messages.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('contacts::messages.fields.status') }}">
                    <option value="">{{ __('contacts::messages.all_statuses') }}</option>
                    @foreach (__('contacts::messages.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.contacts.messages.bulk')" :actions="__('contacts::messages.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('contacts::messages.fields.name') }}</th>
            <th>{{ __('contacts::messages.fields.subject') }}</th>
            <th>{{ __('contacts::messages.fields.message') }}</th>
            <th>{{ __('contacts::messages.fields.status') }}</th>
            <th>{{ __('contacts::messages.fields.created_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($messages as $message)
            <tr>
                <x-admin.table.check :value="$message->id" :label="$message->name" />
                <td class="col-main" data-label="{{ __('contacts::messages.fields.name') }}">
                    <a href="{{ route('admin.contacts.messages.show', $message) }}" @class(['tbl-title', 'fw-bold' => $message->isNew()])>{{ $message->name }}</a>
                    <span class="tbl-sub">{{ $message->email }}</span>
                </td>
                <td data-label="{{ __('contacts::messages.fields.subject') }}">{{ $message->subject ?: '—' }}</td>
                <td data-label="{{ __('contacts::messages.fields.message') }}" class="text-muted">
                    {{ \Illuminate\Support\Str::limit($message->message, 70) }}
                </td>
                <td data-label="{{ __('contacts::messages.fields.status') }}">
                    <span @class(['badge-status', 'warning' => $message->isNew(), 'success' => ! $message->isNew()])>
                        {{ __('contacts::messages.statuses.'.$message->status) }}
                    </span>
                </td>
                <td data-label="{{ __('contacts::messages.fields.created_at') }}" class="tbl-date">
                    {{ $message->created_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $message->created_at?->format('H:i') }}</span>
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        <a href="mailto:{{ $message->email }}" class="icon-btn" title="{{ __('contacts::messages.reply') }}" aria-label="{{ __('contacts::messages.reply') }}">
                            <i class="fas fa-reply"></i>
                        </a>
                        <a href="{{ route('admin.contacts.messages.show', $message) }}" class="icon-btn" title="{{ __('contacts::messages.open') }}" aria-label="{{ __('contacts::messages.open') }}">
                            <i class="fas fa-eye"></i>
                        </a>
                        @can('delete', $message)
                            <x-admin.confirm-delete :action="route('admin.contacts.messages.destroy', $message)" icon-only
                                :message="__('contacts::messages.delete_confirm', ['name' => $message->name])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$messages" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
