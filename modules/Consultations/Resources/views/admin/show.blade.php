@php
    $title = __('consultations::admin.show_title', ['name' => $consultation->name]);
    $fields = [
        'email' => $consultation->email,
        'service' => $consultation->service,
        'locale' => config('cms.locale_names.'.$consultation->locale, strtoupper($consultation->locale)),
        'created_at' => $consultation->created_at?->format('d.m.Y H:i'),
    ];
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('consultations::admin.title') => route('admin.consultations.index'), $title => null]">

    <x-slot:actions>
        <a href="{{ route('admin.consultations.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('consultations::admin.back') }}
        </a>
        @can('delete', $consultation)
            <x-admin.confirm-delete :action="route('admin.consultations.destroy', $consultation)"
                :message="__('consultations::admin.delete_confirm', ['name' => $consultation->name])" />
        @endcan
        @can('update', $consultation)
            <form method="POST" action="{{ route('admin.consultations.status', $consultation) }}" class="d-inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $consultation->isNew() ? 'handled' : 'new' }}">
                <button type="submit" @class(['btn-brand' => $consultation->isNew(), 'btn-soft' => ! $consultation->isNew()])>
                    <i @class(['fas', 'fa-check' => $consultation->isNew(), 'fa-undo' => ! $consultation->isNew()])></i>
                    {{ $consultation->isNew() ? __('consultations::admin.mark_handled') : __('consultations::admin.mark_new') }}
                </button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ __('consultations::admin.fields.message') }}</span>
                </div>
                <div class="card-body">
                    @if ($consultation->message)
                        <p class="mb-0" style="white-space: pre-line;">{{ $consultation->message }}</p>
                    @else
                        <p class="mb-0 text-muted">{{ __('consultations::admin.no_message') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ __('consultations::admin.details') }}</span>
                    <span @class(['badge-status', 'warning' => $consultation->isNew(), 'success' => ! $consultation->isNew()])>
                        {{ __('consultations::admin.statuses.'.$consultation->status) }}
                    </span>
                </div>
                <div class="card-body">
                    @foreach ($fields as $field => $value)
                        <div @class(['mb-3' => ! $loop->last])>
                            <div class="form-label mb-1">{{ __('consultations::admin.fields.'.$field) }}</div>
                            @if ($field === 'email')
                                <a href="mailto:{{ $value }}">{{ $value }}</a>
                            @else
                                <div>{{ $value ?: '—' }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <a href="mailto:{{ $consultation->email }}?subject={{ rawurlencode(__('consultations::admin.reply_subject')) }}" class="btn-soft w-100 justify-content-center mt-3">
                <i class="fas fa-reply"></i> {{ __('consultations::admin.reply') }}
            </a>
        </div>
    </div>
</x-admin-layout::app>
