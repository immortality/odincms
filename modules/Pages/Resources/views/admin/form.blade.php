{{-- The page form (odinadmin/pages-form.html): content and place in the tree on the left, Publish and the image on the right. --}}
@php
    $title = $page->exists ? __('pages::admin.edit') : __('pages::admin.create');
    $status = old('status', $page->status);
    // The parent select: the slug prefix (data-slug) follows the chosen parent.
    $parentOptions = array_map(fn (array $row) => [
        'value' => $row['page']->id,
        'label' => $row['page']->label(),
        'depth' => $row['depth'],
        'trail' => $row['trail'],
        'data' => ['slug' => $row['page']->path],
    ], $parents);
    $parentPath = collect($parents)->first(fn (array $row) => (string) $row['page']->id === (string) old('parent_id', $page->parent_id))['page']->path ?? null;
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('pages::admin.title') => route('admin.pages.index'),
        $title => null,
    ]">

    {{-- Back, View and Delete in the page head; Save is in the Publish card. --}}
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
    </x-slot:actions>

    <form method="POST" id="page-form" action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($page->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            {{-- Main column --}}
            <div class="col-xl-8">
                <div class="card card-auto">
                    <div class="card-header"><span>{{ __('pages::admin.content') }}</span></div>
                    <div class="card-body">
                        {{-- One tab per language: title, text and the search engine texts. --}}
                        <div class="form-section">
                            <h6 class="form-section-title">{{ __('pages::admin.section_content') }}</h6>
                            <x-admin.form.translatable-group :fields="[
                                ['name' => 'title', 'label' => __('pages::admin.fields.title'), 'value' => $page->getTranslations('title'), 'required' => true],
                                ['name' => 'body', 'label' => __('pages::admin.fields.body'), 'type' => 'editor', 'value' => $page->getTranslations('body')],
                                ['name' => 'meta_title', 'label' => __('pages::admin.fields.meta_title'), 'value' => $page->getTranslations('meta_title'),
                                    'help' => __('pages::admin.meta_title_help')],
                                ['name' => 'meta_description', 'label' => __('pages::admin.fields.meta_description'), 'type' => 'textarea', 'rows' => 2,
                                    'value' => $page->getTranslations('meta_description'), 'help' => __('pages::admin.meta_description_help')],
                            ]" />
                        </div>

                        {{-- Place in the page tree --}}
                        <div class="form-section">
                            <h6 class="form-section-title">{{ __('pages::admin.section_tree') }}</h6>
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <x-admin.form.tree-select name="parent_id" id="page-parent" :label="__('pages::admin.fields.parent')"
                                        :options="$parentOptions" :value="$page->parent_id" :placeholder="__('pages::admin.top_level')"
                                        :help="__('pages::admin.parent_help')" />
                                </div>
                                <div class="col-md-4">
                                    <x-admin.form.input name="sort_order" type="number" min="0" step="1" :label="__('pages::admin.fields.sort_order')"
                                        :value="$page->exists ? $page->sort_order : null" :help="__('pages::admin.sort_order_help')" />
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="page-slug">{{ __('pages::admin.fields.slug') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text addon-soft" data-slug-prefix>/{{ $parentPath ? $parentPath.'/' : '' }}</span>
                                        <input type="text" name="slug" id="page-slug" value="{{ old('slug', $page->slug) }}"
                                               @class(['form-control form-soft', 'is-invalid' => $errors->has('slug')])>
                                    </div>
                                    @error('slug')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">{{ __('pages::admin.slug_help') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Side column --}}
            <div class="col-xl-4">
                <div class="card card-auto mb-4">
                    <div class="card-header"><span>{{ __('pages::admin.publish') }}</span></div>
                    <div class="card-body">
                        <span class="form-label d-block">{{ __('pages::admin.fields.status') }} <span class="req">*</span></span>
                        <div class="seg-control seg-block seg-solid mb-3" role="radiogroup" aria-label="{{ __('pages::admin.fields.status') }}">
                            @foreach (__('pages::admin.statuses') as $value => $label)
                                <input class="seg-input" type="radio" name="status" id="page-status-{{ $value }}" value="{{ $value }}" @checked($status === $value)>
                                <label @class(['seg-btn', 'is-success' => $value === \Modules\Pages\Models\Page::PUBLISHED]) for="page-status-{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('status')
                            <div class="invalid-feedback d-block mb-3">{{ $message }}</div>
                        @enderror

                        <x-admin.form.select name="layout" :label="__('pages::admin.fields.layout')" :options="$layouts"
                            :value="$page->layout" required :help="__('pages::admin.layout_help')" />

                        <div class="form-actions">
                            <a href="{{ route('admin.pages.index') }}" class="btn-soft">{{ __('pages::admin.cancel') }}</a>
                            <button type="submit" class="btn-brand"><i class="fas fa-save"></i> {{ __('pages::admin.save') }}</button>
                        </div>
                    </div>
                </div>

                <div class="card card-auto">
                    <div class="card-header"><span>{{ __('pages::admin.image_label') }}</span></div>
                    <div class="card-body">
                        <x-admin.form.cropped-image name="image" :model="$page" />
                    </div>
                </div>
            </div>
        </div>
    </form>

    @push('admin-scripts')
        <script>
            // The slug prefix follows the parent page: /about/team/
            (() => {
                const parent = document.getElementById('page-parent');
                const prefix = document.querySelector('[data-slug-prefix]');
                if (!parent || !prefix) return;
                parent.addEventListener('change', () => {
                    const slug = parent.selectedOptions[0]?.dataset.slug;
                    prefix.textContent = slug ? `/${slug}/` : '/';
                });
            })();
        </script>
    @endpush
</x-admin-layout::app>
