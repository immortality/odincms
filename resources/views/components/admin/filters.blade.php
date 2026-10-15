{{--
    Search and filter form (GET) for the table toolbar. Put extra filters in the slot, as
    <select name="status" class="form-select form-soft tt-select">.

    <x-admin.filters :placeholder="__('blog::admin.search')">
        <select name="status" class="form-select form-soft tt-select" aria-label="…">…</select>
    </x-admin.filters>
--}}
@props([
    'action' => null,
    'placeholder' => __('Search'),
])

@php
    $active = collect(request()->except('page'))->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp

<form method="GET" action="{{ $action ?? url()->current() }}" class="tt-filters">
    @if (request()->filled('per_page'))
        <input type="hidden" name="per_page" value="{{ request('per_page') }}">
    @endif

    <div class="input-icon tt-search">
        <i class="fas fa-search"></i>
        <input type="search" name="search" value="{{ request('search') }}" class="form-control form-soft"
            placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
    </div>

    {{ $slot }}

    <button type="submit" class="btn-soft"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>

    @if ($active)
        <a href="{{ $action ?? url()->current() }}" class="btn-ghost"><i class="fas fa-undo"></i> {{ __('Reset') }}</a>
    @endif
</form>
