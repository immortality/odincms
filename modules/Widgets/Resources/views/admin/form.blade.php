@php
    $title = $instance->exists ? __('widgets::admin.edit') : __('widgets::admin.add', ['widget' => $widget::label()]);
    $pages = $visibility['pages'] ?? [];
    $localeOptions = collect(config('cms.locales'))->mapWithKeys(fn ($locale) => [$locale => config("cms.locale_names.{$locale}", strtoupper($locale))])->all();

    // Title + the translatable settings (e.g. slogan, intro, detail), for one translatable-group.
    $translatableFields = [[
        'name' => 'title', 'label' => __('widgets::admin.fields.title'),
        'value' => $instance->getTranslations('title'), 'help' => __('widgets::admin.title_help'),
    ]];
    foreach ($fields as $name => $field) {
        if ($field['type'] === 'translatable') {
            $translatableFields[] = [
                'name' => 'settings['.$name.']',
                'label' => __((string) ($field['label'] ?? $name)),
                'value' => (array) $widget->setting($name),
                'type' => $field['input'] ?? 'input',
                'help' => isset($field['help']) ? __((string) $field['help']) : null,
                'rows' => $field['rows'] ?? 4,
                'required' => (bool) ($field['required'] ?? false),
            ];
        }
    }
@endphp

<x-admin-layout::app
    :title="$title"
    :description="$widget::label()"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('widgets::admin.title') => route('admin.widgets.index'), $title => null]">

    {{-- Back and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.widgets.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('widgets::admin.back') }}
        </a>
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="widget-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('widgets::admin.save') }}
        </button>
    </x-slot:actions>

    {{-- The visibility fields shown depend on the choices (Alpine, resources/js/admin.js). --}}
    <form method="POST" id="widget-form" action="{{ $instance->exists ? route('admin.widgets.update', $instance) : route('admin.widgets.store') }}"
          x-data="{ pageMode: @js(old('page_mode', $pages['mode'] ?? 'all')), audience: @js(old('audience', $visibility['audience'] ?? 'everyone')) }">
        @csrf
        @if ($instance->exists)
            @method('PUT')
        @else
            <input type="hidden" name="widget" value="{{ $instance->widget }}">
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- The title and every translatable setting under one set of language tabs; the other settings below. --}}
                        <x-admin.form.translatable-group :fields="$translatableFields" />

                        @foreach ($fields as $name => $field)
                            @continue($field['type'] === 'translatable')
                            <x-admin.form.field :name="'settings['.$name.']'" :field="$field" :value="$widget->setting($name)" />
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="area_id" :label="__('widgets::admin.fields.area')" :options="$areas" :value="$instance->area_id" required />
                        <x-admin.form.checkbox name="is_active" :label="__('widgets::admin.fields.is_active')" :checked="$instance->is_active" />
                        <x-admin.form.input name="cache_ttl" type="number" min="0" :label="__('widgets::admin.fields.cache_ttl')"
                            :value="$instance->cache_ttl" :help="__('widgets::admin.cache_help')" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('widgets::admin.visibility') }}</span>
                    </div>
                    <div class="card-body">
                        <x-admin.form.select name="page_mode" :label="__('widgets::admin.fields.pages')" :options="__('widgets::admin.page_modes')"
                            :value="$pages['mode'] ?? 'all'" x-model="pageMode" />
                        <div x-show="pageMode !== 'all'" x-cloak>
                            <x-admin.form.textarea name="patterns_text" rows="3" :label="__('widgets::admin.fields.patterns')"
                                :value="implode(PHP_EOL, $pages['patterns'] ?? [])" :help="__('widgets::admin.patterns_help')" />
                            @error('patterns')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        </div>

                        <x-admin.form.select name="locales" :label="__('widgets::admin.fields.locales')" :options="$localeOptions"
                            :value="$visibility['locales'] ?? []" multiple searchable :placeholder="__('widgets::admin.all_locales')" />

                        <x-admin.form.select name="audience" :label="__('widgets::admin.fields.audience')" :options="__('widgets::admin.audiences')"
                            :value="$visibility['audience'] ?? 'everyone'" x-model="audience" />
                        <div x-show="audience === 'groups'" x-cloak>
                            <x-admin.form.select name="groups" :label="__('widgets::admin.fields.groups')" :options="$groups"
                                :value="$visibility['groups'] ?? []" multiple searchable />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
