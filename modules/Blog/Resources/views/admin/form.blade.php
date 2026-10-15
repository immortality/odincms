@php
    $title = $post->exists ? __('blog::admin.edit') : __('blog::admin.create');
    $statuses = $canPublish || $post->status === \Modules\Blog\Models\Post::PUBLISHED
        ? __('blog::admin.statuses')
        : ['draft' => __('blog::admin.statuses.draft')];
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('blog::admin.title') => route('admin.blog.index'),
        $title => null,
    ]">

    {{-- Back, View, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.blog.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('blog::admin.back') }}
        </a>
        @if ($post->exists)
            <a href="{{ $post->url() }}" target="_blank" rel="noopener" class="btn-soft">
                <i class="fas fa-external-link-alt"></i> {{ $post->isPublished() ? __('blog::admin.view') : __('blog::admin.preview') }}
            </a>
            @can('delete', $post)
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.blog.destroy', $post)"
                    :message="__('blog::admin.delete_confirm', ['title' => $post->title])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="post-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('blog::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="post-form" action="{{ $post->exists ? route('admin.blog.update', $post) : route('admin.blog.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($post->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the title, the summary and the text. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('blog::admin.fields.title'), 'value' => $post->getTranslations('title'), 'required' => true],
                            ['name' => 'excerpt', 'label' => __('blog::admin.fields.excerpt'), 'type' => 'textarea', 'rows' => 3,
                                'value' => $post->getTranslations('excerpt'), 'help' => __('blog::admin.excerpt_help')],
                            ['name' => 'body', 'label' => __('blog::admin.fields.body'), 'type' => 'editor', 'value' => $post->getTranslations('body')],
                        ]" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('blog::admin.seo') }}</span>
                    </div>
                    <div class="card-body">
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'meta_title', 'label' => __('blog::admin.fields.meta_title'), 'value' => $post->getTranslations('meta_title'),
                                'help' => __('blog::admin.meta_title_help')],
                            ['name' => 'meta_description', 'label' => __('blog::admin.fields.meta_description'), 'type' => 'textarea', 'rows' => 2,
                                'value' => $post->getTranslations('meta_description'), 'help' => __('blog::admin.meta_description_help')],
                        ]" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="status" :label="__('blog::admin.fields.status')" :options="$statuses"
                            :value="$post->status" required :help="$canPublish ? null : __('blog::admin.author_help')" />
                        <x-admin.form.input name="published_at" type="datetime-local" :label="__('blog::admin.fields.published_at')"
                            :value="$post->published_at?->format('Y-m-d\TH:i')" :help="__('blog::admin.published_at_help')" />
                        <x-admin.form.input name="slug" :label="__('blog::admin.fields.slug')" :value="$post->slug"
                            :help="__('blog::admin.slug_help')" />
                        <x-admin.form.select name="category_id" :label="__('blog::admin.fields.category')" :options="$categories"
                            :value="$post->category_id" :placeholder="__('blog::admin.no_category')" />

                        @if ($post->exists)
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-user"></i></div>
                                <span class="sum-label">{{ __('blog::admin.fields.author') }}</span>
                                <span class="sum-val">{{ $post->author?->name ?? '—' }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-clock"></i></div>
                                <span class="sum-label">{{ __('blog::admin.updated', ['date' => $post->updated_at?->format('d.m.Y H:i')]) }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-body">
                        <x-admin.form.cropped-image name="cover" :model="$post" :label="__('blog::admin.fields.cover')" />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
