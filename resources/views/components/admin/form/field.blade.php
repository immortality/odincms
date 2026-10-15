{{--
    One field from a FormFields definition (Settings sections, widget settings), rendered with the
    shared form components.

    <x-admin.form.field name="limit" :field="['type' => 'number', 'label' => 'Limit']" :value="5" />
--}}
@props([
    'name',
    'field',
    'value' => null,
])

@php
    $label = __((string) ($field['label'] ?? $name));
    $help = isset($field['help']) ? __((string) $field['help']) : null;
    $required = in_array('required', (array) ($field['rules'] ?? []), true) || (bool) ($field['required'] ?? false);
    $options = \App\Cms\Support\FormFields::options($field);
@endphp

@switch($field['type'])
    @case('textarea')
        <x-admin.form.textarea :name="$name" :label="$label" :value="$value" :help="$help" :required="$required" />
        @break

    @case('editor')
        <x-admin.form.editor :name="$name" :label="$label" :value="$value" :help="$help" :required="$required" mode="basic" />
        @break

    @case('email')
    @case('number')
        <x-admin.form.input :name="$name" :type="$field['type']" :label="$label" :value="$value" :help="$help" :required="$required" />
        @break

    @case('select')
        {{-- "placeholder": an empty first option (e.g. "All categories") --}}
        <x-admin.form.select :name="$name" :label="$label" :options="$options" :value="$value" :help="$help" :required="$required"
            :placeholder="isset($field['placeholder']) ? __((string) $field['placeholder']) : null" />
        @break

    @case('multiselect')
        <x-admin.form.select :name="$name" :label="$label" :options="$options" :value="(array) $value" :help="$help"
            :required="$required" multiple searchable />
        @break

    @case('checkbox')
        <x-admin.form.checkbox :name="$name" :label="$label" :checked="(bool) $value" :help="$help" />
        @break

    @case('translatable')
        <x-admin.form.translatable :name="$name" :type="$field['input'] ?? 'input'" :label="$label" :value="(array) $value"
            :help="$help" :required="$required" />
        @break

    @default
        <x-admin.form.input :name="$name" :label="$label" :value="$value" :help="$help" :required="$required" />
@endswitch
