@php
    $title = $page->exists ? __('pages::admin.edit') : __('pages::admin.create');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('pages::admin.title') => route('admin.pages.index'),
        $title => null,
    ]">

    {{-- Back, View, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.pages.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('pages::admin.back') }}
        </a>
        @if ($page->exists)
            <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="btn-soft">
                <i class="fas fa-external-link-alt"></i> {{ $page->isPublished() ? __('pages::admin.view') : __('pages::admin.preview') }}
            </a>
            @can('pages.delete')
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.pages.destroy', $page)"
                    :message="__('pages::admin.delete_confirm', ['title' => $page->label()])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="page-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('pages::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="page-form" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($page->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the title and the text. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('pages::admin.fields.title'), 'value' => $page->getTranslations('title'), 'required' => true],
                            ['name' => 'body', 'label' => __('pages::admin.fields.body'), 'type' => 'editor', 'value' => $page->getTranslations('body')],
                        ]" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('pages::admin.seo') }}</span>
                    </div>
                    <div class="card-body">
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'meta_title', 'label' => __('pages::admin.fields.meta_title'), 'value' => $page->getTranslations('meta_title'),
                                'help' => __('pages::admin.meta_title_help')],
                            ['name' => 'meta_description', 'label' => __('pages::admin.fields.meta_description'), 'type' => 'textarea', 'rows' => 2,
                                'value' => $page->getTranslations('meta_description'), 'help' => __('pages::admin.meta_description_help')],
                        ]" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="status" :label="__('pages::admin.fields.status')" :options="__('pages::admin.statuses')"
                            :value="$page->status" required />
                        <x-admin.form.select name="layout" :label="__('pages::admin.fields.layout')" :options="$layouts"
                            :value="$page->layout" required :help="__('pages::admin.layout_help')" />
                        <x-admin.form.select name="parent_id" :label="__('pages::admin.fields.parent')" :options="$parents"
                            :value="$page->parent_id" :placeholder="__('pages::admin.top_level')" />
                        <x-admin.form.input name="slug" :label="__('pages::admin.fields.slug')" :value="$page->slug"
                            :help="$page->exists ? __('pages::admin.address', ['path' => '/'.$page->path]) : __('pages::admin.slug_help')" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-body">
                        <x-admin.form.cropped-image name="image" :model="$page" :label="__('pages::admin.fields.image')" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
