@php
    $title = $category->exists ? __('categories::admin.edit') : __('categories::admin.create');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('categories::admin.title') => route('admin.categories.index'),
        $typeLabel => route('admin.categories.tree', $type),
        $title => null,
    ]">

    {{-- Back, View, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.categories.tree', $type) }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('categories::admin.back') }}
        </a>
        @if ($publicUrl)
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn-soft">
                <i class="fas fa-external-link-alt"></i> {{ __('categories::admin.view') }}
            </a>
        @endif
        @if ($category->exists)
            @can('categories.delete')
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.categories.destroy', [$type, $category])"
                    :message="__('categories::admin.delete_confirm', ['title' => $category->label()])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="category-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('categories::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="category-form" action="{{ $category->exists ? route('admin.categories.update', [$type, $category]) : route('admin.categories.store', $type) }}">
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the title and the description. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('categories::admin.fields.title'), 'value' => $category->getTranslations('title'), 'required' => true],
                            ['name' => 'description', 'label' => __('categories::admin.fields.description'), 'type' => 'textarea', 'rows' => 3,
                                'value' => $category->getTranslations('description')],
                        ]" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.input name="slug" :label="__('categories::admin.fields.slug')" :value="$category->slug"
                            :help="__('categories::admin.slug_help')" />
                        <x-admin.form.select name="parent_id" :label="__('categories::admin.fields.parent')" :options="$parents"
                            :value="$category->parent_id" :placeholder="__('categories::admin.top_level')" />
                        <x-admin.form.checkbox name="is_active" :label="__('categories::admin.fields.is_active')" :checked="$category->is_active" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
