@php
    $title = __('contacts::messages.show_title', ['name' => $message->name]);
    $fields = [
        'email' => $message->email,
        'phone' => $message->phone,
        'locale' => config('cms.locale_names.'.$message->locale, strtoupper($message->locale)),
        'created_at' => $message->created_at?->format('d.m.Y H:i'),
    ];
    $replySubject = $message->subject
        ? __('contacts::messages.reply_subject', ['subject' => $message->subject])
        : __('contacts::messages.reply_subject_default');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('contacts::admin.title') => route('admin.contacts.index'), __('contacts::messages.title') => route('admin.contacts.messages.index'), $title => null]">

    <x-slot:actions>
        <a href="{{ route('admin.contacts.messages.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('contacts::messages.back') }}
        </a>
        @can('delete', $message)
            <x-admin.confirm-delete :action="route('admin.contacts.messages.destroy', $message)"
                :message="__('contacts::messages.delete_confirm', ['name' => $message->name])" />
        @endcan
        @can('update', $message)
            <form method="POST" action="{{ route('admin.contacts.messages.status', $message) }}" class="d-inline">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $message->isNew() ? 'handled' : 'new' }}">
                <button type="submit" @class(['btn-brand' => $message->isNew(), 'btn-soft' => ! $message->isNew()])>
                    <i @class(['fas', 'fa-check' => $message->isNew(), 'fa-undo' => ! $message->isNew()])></i>
                    {{ $message->isNew() ? __('contacts::messages.mark_handled') : __('contacts::messages.mark_new') }}
                </button>
            </form>
        @endcan
    </x-slot:actions>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ $message->subject ?: __('contacts::messages.fields.message') }}</span>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-line;">{{ $message->message }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ __('contacts::messages.details') }}</span>
                    <span @class(['badge-status', 'warning' => $message->isNew(), 'success' => ! $message->isNew()])>
                        {{ __('contacts::messages.statuses.'.$message->status) }}
                    </span>
                </div>
                <div class="card-body">
                    @foreach ($fields as $field => $value)
                        <div @class(['mb-3' => ! $loop->last])>
                            <div class="form-label mb-1">{{ __('contacts::messages.fields.'.$field) }}</div>
                            @if ($field === 'email')
                                <a href="mailto:{{ $value }}">{{ $value }}</a>
                            @elseif ($field === 'phone' && $value)
                                <a href="tel:{{ preg_replace('/[^\d+]/', '', $value) }}">{{ $value }}</a>
                            @else
                                <div>{{ $value ?: '—' }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <a href="mailto:{{ $message->email }}?subject={{ rawurlencode($replySubject) }}" class="btn-soft w-100 justify-content-center mt-3">
                <i class="fas fa-reply"></i> {{ __('contacts::messages.reply') }}
            </a>
        </div>
    </div>
</x-admin-layout::app>
