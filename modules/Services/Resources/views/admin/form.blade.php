@php
    $title = $item->exists ? __('services::admin.edit') : __('services::admin.new');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('services::admin.title') => route('admin.services.index'),
        $title => null,
    ]">

    <x-slot:actions>
        <a href="{{ route('admin.services.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('services::admin.back') }}
        </a>
        @if ($item->exists)
            <a href="{{ $item->url() }}" target="_blank" rel="noopener" class="btn-soft">
                <i class="fas fa-external-link-alt"></i> {{ $item->isPublished() ? __('services::admin.view') : __('services::admin.preview') }}
            </a>
            @can('delete', $item)
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.services.destroy', $item)"
                    :message="__('services::admin.delete_confirm', ['title' => $item->title])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="service-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('services::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="service-form" action="{{ $item->exists ? route('admin.services.update', $item) : route('admin.services.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the title and the detail. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('services::admin.fields.title'), 'value' => $item->getTranslations('title'), 'required' => true],
                            ['name' => 'detail', 'label' => __('services::admin.fields.detail'), 'type' => 'editor', 'value' => $item->getTranslations('detail')],
                        ]" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('services::admin.seo') }}</span>
                    </div>
                    <div class="card-body">
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'meta_title', 'label' => __('services::admin.fields.meta_title'), 'value' => $item->getTranslations('meta_title'),
                                'help' => __('services::admin.meta_title_help')],
                            ['name' => 'meta_description', 'label' => __('services::admin.fields.meta_description'), 'type' => 'textarea', 'rows' => 2,
                                'value' => $item->getTranslations('meta_description'), 'help' => __('services::admin.meta_description_help')],
                        ]" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="status" :label="__('services::admin.fields.status')" :options="__('services::admin.statuses')"
                            :value="$item->status" required />
                        <x-admin.form.input name="slug" :label="__('services::admin.fields.slug')" :value="$item->slug"
                            :help="__('services::admin.slug_help')" />
                        <x-admin.form.cropped-image name="photo" :model="$item" :label="__('services::admin.fields.photo')" />
                    </div>
                </div>

                @if ($item->exists)
                    <div class="card card-auto mt-4">
                        <div class="card-body">
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-calendar-plus"></i></div>
                                <span class="sum-label">{{ __('services::admin.fields.created_at') }}</span>
                                <span class="sum-val">{{ $item->created_at?->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-clock"></i></div>
                                <span class="sum-label">{{ __('services::admin.fields.updated_at') }}</span>
                                <span class="sum-val">{{ $item->updated_at?->format('d.m.Y H:i') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</x-admin-layout::app>
