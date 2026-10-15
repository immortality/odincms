{{--
    Delete button that asks for confirmation (SweetAlert2, resources/js/admin.js) and sends a DELETE request.

    <x-admin.confirm-delete :action="route('admin.blog.posts.destroy', $post)" icon-only />   (table row: .icon-btn)
    <x-admin.confirm-delete :action="…" />                                                   (.btn-soft.danger with label)
--}}
@props([
    'action',
    'label' => __('Delete'),
    'message' => __('Delete this item?'),
    'text' => __('This cannot be undone.'),
    'iconOnly' => false,
])

<form method="POST" action="{{ $action }}" class="d-inline-block"
      data-confirm="{{ $message }}" data-confirm-text="{{ $text }}" data-confirm-button="{{ $label }}">
    @csrf
    @method('DELETE')
    <button type="submit" title="{{ $label }}" aria-label="{{ $label }}" {{ $attributes->class([
        'icon-btn danger' => $iconOnly,
        'btn-soft danger' => ! $iconOnly,
    ]) }}>
        <i class="far fa-trash-alt"></i>
        @unless ($iconOnly)
            {{ $label }}
        @endunless
    </button>
</form>
