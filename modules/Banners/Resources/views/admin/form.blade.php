@php
    $title = $item->exists ? __('banners::admin.edit') : __('banners::admin.new');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('banners::admin.title') => route('admin.banners.index'),
        $title => null,
    ]">

    <x-slot:actions>
        <a href="{{ route('admin.banners.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('banners::admin.back') }}
        </a>
        @if ($item->exists)
            @can('delete', $item)
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.banners.destroy', $item)"
                    :message="__('banners::admin.delete_confirm', ['title' => $item->label()])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="banner-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('banners::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="banner-form" action="{{ $item->exists ? route('admin.banners.update', $item) : route('admin.banners.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the texts of the banner. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('banners::admin.fields.title'), 'value' => $item->getTranslations('title'), 'required' => true],
                            ['name' => 'highlight', 'label' => __('banners::admin.fields.highlight'), 'value' => $item->getTranslations('highlight'),
                                'help' => __('banners::admin.highlight_help')],
                            ['name' => 'text', 'label' => __('banners::admin.fields.text'), 'type' => 'textarea', 'rows' => 3, 'value' => $item->getTranslations('text')],
                            ['name' => 'button_label', 'label' => __('banners::admin.fields.button_label'), 'value' => $item->getTranslations('button_label'),
                                'help' => __('banners::admin.button_label_help')],
                        ]" />
                        <x-admin.form.input name="button_url" :label="__('banners::admin.fields.button_url')" :value="$item->button_url"
                            icon="fas fa-link" :help="__('banners::admin.button_url_help')" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('banners::admin.extras') }}</span>
                    </div>
                    <div class="card-body">
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'badge_label', 'label' => __('banners::admin.fields.badge_label'), 'value' => $item->getTranslations('badge_label'),
                                'help' => __('banners::admin.badge_label_help')],
                            ['name' => 'badge_text', 'label' => __('banners::admin.fields.badge_text'), 'value' => $item->getTranslations('badge_text'),
                                'help' => __('banners::admin.badge_text_help')],
                            ['name' => 'chip_1_text', 'label' => __('banners::admin.fields.chip_1_text'), 'value' => $item->getTranslations('chip_1_text')],
                            ['name' => 'chip_2_text', 'label' => __('banners::admin.fields.chip_2_text'), 'value' => $item->getTranslations('chip_2_text')],
                        ]" />
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <x-admin.form.input name="chip_1_icon" :label="__('banners::admin.fields.chip_1_icon')" :value="$item->chip_1_icon"
                                    :help="__('banners::admin.icon_help')" />
                            </div>
                            <div class="col-sm-6">
                                <x-admin.form.input name="chip_2_icon" :label="__('banners::admin.fields.chip_2_icon')" :value="$item->chip_2_icon"
                                    :help="__('banners::admin.icon_help')" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="status" :label="__('banners::admin.fields.status')" :options="__('banners::admin.statuses')"
                            :value="$item->status" required />
                        <x-admin.form.input name="position" type="number" :label="__('banners::admin.fields.position')" :value="$item->position ?? 0"
                            :help="__('banners::admin.position_help')" />
                        <x-admin.form.cropped-image name="image" :model="$item" :label="__('banners::admin.fields.image')" />
                    </div>
                </div>

                @if ($item->exists)
                    <div class="card card-auto mt-4">
                        <div class="card-body">
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-calendar-plus"></i></div>
                                <span class="sum-label">{{ __('banners::admin.fields.created_at') }}</span>
                                <span class="sum-val">{{ $item->created_at?->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-clock"></i></div>
                                <span class="sum-label">{{ __('banners::admin.fields.updated_at') }}</span>
                                <span class="sum-val">{{ $item->updated_at?->format('d.m.Y H:i') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</x-admin-layout::app>
