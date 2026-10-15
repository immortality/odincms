{{-- Row checkbox for bulk actions. Its value is posted as ids[] with the bulk form. --}}
@props([
    'value',
    'form' => 'bulk-form',
    'label' => null,
])

<td class="col-check">
    <input type="checkbox" class="form-check-input check-soft js-row-check" name="ids[]" value="{{ $value }}" form="{{ $form }}"
        aria-label="{{ $label ?? __('Select') }}">
</td>
