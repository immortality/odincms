@php
    $title = $area->exists ? __('widgets::admin.edit_area') : __('widgets::admin.new_area');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('widgets::admin.title') => route('admin.widgets.index'),
        __('widgets::admin.areas') => route('admin.widgets.areas.index'),
        $title => null,
    ]">

    {{-- Back and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.widgets.areas.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('widgets::admin.back') }}
        </a>
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="area-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('widgets::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="area-form" action="{{ $area->exists ? route('admin.widgets.areas.update', $area) : route('admin.widgets.areas.store') }}">
        @csrf
        @if ($area->exists)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.translatable name="name" :label="__('widgets::admin.fields.area_name')"
                            :value="$area->exists ? $area->getTranslations('name') : []" required />
                        <x-admin.form.input name="slug" :label="__('widgets::admin.fields.slug')" :value="$area->slug"
                            :help="__('widgets::admin.slug_help')" required />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
