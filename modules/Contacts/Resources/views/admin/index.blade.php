<x-admin-layout::app
    :title="__('contacts::admin.title')"
    :description="__('contacts::admin.description', ['count' => $contacts->total()])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('contacts::admin.title') => null]">

    @can('create', \Modules\Contacts\Models\Contact::class)
        <x-slot:actions>
            <a href="{{ route('admin.contacts.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('contacts::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table bulk-form="bulk-form" :empty="__('contacts::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('contacts::admin.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('contacts::admin.fields.status') }}">
                    <option value="">{{ __('contacts::admin.all_statuses') }}</option>
                    @foreach (__('contacts::admin.statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.contacts.bulk')" :actions="__('contacts::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('contacts::admin.fields.title') }}</th>
            <th>{{ __('contacts::admin.fields.city') }}</th>
            <th>{{ __('contacts::admin.fields.phone') }}</th>
            <th>{{ __('contacts::admin.fields.email') }}</th>
            <th>{{ __('contacts::admin.fields.status') }}</th>
            <th>{{ __('contacts::admin.fields.updated_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($contacts as $contact)
            <tr>
                <x-admin.table.check :value="$contact->id" :label="$contact->title" />
                <td class="col-main" data-label="{{ __('contacts::admin.fields.title') }}">
                    <div class="tbl-item">
                        @if ($photo = $contact->photoUrl())
                            <img src="{{ $photo }}" alt="" class="tbl-thumb" loading="lazy">
                        @else
                            <div class="tbl-thumb d-grid" style="place-items:center;color:var(--text-muted);"><i class="fas fa-address-book"></i></div>
                        @endif
                        <div>
                            @can('update', $contact)
                                <a href="{{ route('admin.contacts.edit', $contact) }}" class="tbl-title">{{ $contact->title }}</a>
                            @else
                                <span class="tbl-title">{{ $contact->title }}</span>
                            @endcan
                            <span class="tbl-sub">{{ $contact->address ?: '—' }}</span>
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('contacts::admin.fields.city') }}">{{ $contact->city ?: '—' }}</td>
                <td data-label="{{ __('contacts::admin.fields.phone') }}" class="text-nowrap">
                    @if ($contact->phone)
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact->phone) }}" class="text-reset text-decoration-none">{{ $contact->phone }}</a>
                    @else
                        —
                    @endif
                </td>
                <td data-label="{{ __('contacts::admin.fields.email') }}">
                    @if ($contact->email)
                        <a href="mailto:{{ $contact->email }}" class="text-reset text-decoration-none">{{ $contact->email }}</a>
                    @else
                        —
                    @endif
                </td>
                <td data-label="{{ __('contacts::admin.fields.status') }}">
                    <span @class(['badge-status', 'success' => $contact->isPublished(), 'muted' => ! $contact->isPublished()])>
                        {{ __('contacts::admin.statuses.'.$contact->status) }}
                    </span>
                </td>
                <td data-label="{{ __('contacts::admin.fields.updated_at') }}" class="tbl-date">
                    {{ $contact->updated_at?->format('d.m.Y') }}<span class="tbl-sub">{{ $contact->updated_at?->format('H:i') }}</span>
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        @if ($map = $contact->mapUrl())
                            <a href="{{ $map }}" target="_blank" rel="noopener" class="icon-btn" title="{{ __('contacts::admin.open_map') }}" aria-label="{{ __('contacts::admin.open_map') }}">
                                <i class="fas fa-map-marker-alt"></i>
                            </a>
                        @endif
                        @can('update', $contact)
                            <a href="{{ route('admin.contacts.edit', $contact) }}" class="icon-btn" title="{{ __('contacts::admin.edit') }}" aria-label="{{ __('contacts::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('delete', $contact)
                            <x-admin.confirm-delete :action="route('admin.contacts.destroy', $contact)" icon-only
                                :message="__('contacts::admin.delete_confirm', ['title' => $contact->title])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$contacts" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
