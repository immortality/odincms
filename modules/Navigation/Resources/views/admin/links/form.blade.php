@php
    $title = $link->exists ? __('navigation::admin.edit_link') : __('navigation::admin.new_link');
    $type = old('type', $link->type);
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('navigation::admin.title') => route('admin.navigation.index'),
        $group->title => route('admin.navigation.links.index', $group),
        $title => null,
    ]">

    {{-- Back, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.navigation.links.index', $group) }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('navigation::admin.back') }}
        </a>
        @if ($link->exists)
            @can('navigation.delete')
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.navigation.links.destroy', [$group, $link])"
                    :text="__('navigation::admin.delete_link_text')" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="link-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('navigation::admin.save') }}
        </button>
    </x-slot:actions>

    {{-- The fields shown depend on the link type (Alpine, resources/js/admin.js). --}}
    <form method="POST" id="link-form" action="{{ $link->exists ? route('admin.navigation.links.update', [$group, $link]) : route('admin.navigation.links.store', $group) }}"
          x-data="{ type: @js($type) }">
        @csrf
        @if ($link->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="type" :label="__('navigation::admin.fields.type')" :value="$type"
                            :options="__('navigation::admin.types')" x-model="type" required />

                        {{-- A page, post or category: choose the kind, then the item. --}}
                        <div x-show="type === 'model'" x-cloak x-data="{ linkableType: @js(old('linkable_type', $defaultLinkableType)) }">
                            @if ($linkableTypes === [])
                                <div class="alert-soft light mb-3" role="alert">
                                    <i class="fas fa-info-circle"></i>
                                    <div class="alert-body">{{ __('navigation::admin.no_linkables') }}</div>
                                </div>
                            @else
                                <x-admin.form.select name="linkable_type" :label="__('navigation::admin.fields.linkable_type')" :options="$linkableTypes"
                                    :value="$defaultLinkableType" x-model="linkableType" />

                                <div class="mb-3">
                                    <span class="form-label d-block">{{ __('navigation::admin.fields.linkable_id') }}</span>
                                    @foreach ($linkableOptions as $class => $options)
                                        {{-- Only the select of the chosen kind is enabled, so only it is posted. --}}
                                        <div x-show="linkableType === @js($class)">
                                            @if ($options === [])
                                                <div class="form-text mt-0">{{ __('navigation::admin.no_items') }}</div>
                                            @else
                                                <select name="linkable_id" class="form-select form-soft @error('linkable_id') is-invalid @enderror"
                                                    aria-label="{{ __('navigation::admin.fields.linkable_id') }}"
                                                    x-bind:disabled="linkableType !== @js($class)">
                                                    <option value="">{{ __('navigation::admin.choose_item') }}</option>
                                                    @foreach ($options as $id => $label)
                                                        <option value="{{ $id }}" @selected((string) old('linkable_id', $link->linkable_type === $class ? $link->linkable_id : null) === (string) $id)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @endif
                                        </div>
                                    @endforeach
                                    @error('linkable_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endif
                        </div>

                        <div x-show="type === 'path'" x-cloak>
                            <div class="mb-3">
                                <label class="form-label" for="path">{{ __('navigation::admin.fields.path') }}</label>
                                <div class="input-group">
                                    <span class="input-group-text addon-soft">{{ url('/') }}/</span>
                                    <input type="text" name="path" id="path" value="{{ old('path', $link->type === 'path' && $link->url !== '/' ? $link->url : '') }}"
                                        placeholder="about-us" @class(['form-control form-soft', 'is-invalid' => $errors->has('path')])>
                                </div>
                                @error('path')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text">{{ __('navigation::admin.path_help') }}</div>
                            </div>
                        </div>

                        <div x-show="type === 'route'" x-cloak>
                            <x-admin.form.select name="route_name" :label="__('navigation::admin.fields.route_name')" :options="$routes"
                                :value="$link->route_name" searchable :placeholder="__('navigation::admin.choose_route')" />
                            <input type="hidden" name="route_parameters_text" value="{{ $routeParameters }}">
                        </div>

                        <div x-show="type === 'url'" x-cloak>
                            <x-admin.form.input name="url" :label="__('navigation::admin.fields.url')" :value="$link->type === 'url' ? $link->url : null"
                                placeholder="https://…" :help="__('navigation::admin.url_help')" />
                        </div>

                        <x-admin.form.translatable name="title" :label="__('navigation::admin.fields.title')"
                            :value="$link->getTranslations('title')" :help="__('navigation::admin.title_help')" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="parent_id" :label="__('navigation::admin.fields.parent')" :options="$parents"
                            :value="$link->parent_id" :placeholder="__('navigation::admin.top_level')" />
                        <x-admin.form.select name="visibility" :label="__('navigation::admin.fields.visibility')" :options="$visibilityOptions"
                            :value="$link->visibility ?? []" multiple searchable :placeholder="__('navigation::admin.everyone')"
                            :help="__('navigation::admin.visibility_help')" />
                        <x-admin.form.input name="css_class" :label="__('navigation::admin.fields.css_class')" :value="$link->css_class" />
                        <x-admin.form.checkbox name="open_new_tab" :label="__('navigation::admin.fields.open_new_tab')" :checked="$link->open_new_tab" />
                        <x-admin.form.checkbox name="is_active" :label="__('navigation::admin.fields.is_active')" :checked="$link->is_active" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
