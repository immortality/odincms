{{--
    Bulk actions for checked table rows: a bar over the table, shown while rows are checked
    (resources/js/admin.js). Posts `action` and `ids[]` to $action. Put it in <x-admin.table>'s "bulk" slot.

    <x-admin.bulk-actions :action="route('admin.blog.posts.bulk')" :actions="['publish' => 'Publish', 'delete' => 'Delete']" />
--}}
@props([
    'action',
    'actions' => [],
    'form' => 'bulk-form',
    'confirm' => __('Apply this action to the selected items?'),
])

<form id="{{ $form }}" method="POST" action="{{ $action }}" data-confirm="{{ $confirm }}" class="d-none">
    @csrf
</form>

<div class="bulk-bar js-bulk-action" data-form="{{ $form }}">
    <span class="bulk-count js-bulk-count" data-text="{{ __(':count selected') }}"></span>
    <select name="action" form="{{ $form }}" class="form-select form-soft form-sm w-auto" aria-label="{{ __('Bulk action') }}">
        <option value="">{{ __('Bulk action') }}</option>
        @foreach ($actions as $value => $label)
            <option value="{{ $value }}">{{ $label }}</option>
        @endforeach
    </select>
    <button type="submit" form="{{ $form }}" class="btn-soft btn-sm js-bulk-apply" disabled>{{ __('Apply') }}</button>
    <button type="button" class="btn-ghost ms-auto js-bulk-clear">{{ __('Clear selection') }}</button>
</div>
