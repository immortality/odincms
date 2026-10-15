@php
    $title = $group->exists ? __('navigation::admin.edit_group') : __('navigation::admin.new_group');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('navigation::admin.title') => route('admin.navigation.index'),
        $title => null,
    ]">

    {{-- Back, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.navigation.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('navigation::admin.back') }}
        </a>
        @if ($group->exists)
            @can('navigation.delete')
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.navigation.destroy', $group)"
                    :text="__('navigation::admin.delete_group_text', ['count' => $group->links()->count()])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="menu-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('navigation::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="menu-form" action="{{ $group->exists ? route('admin.navigation.update', $group) : route('admin.navigation.store') }}">
        @csrf
        @if ($group->exists)
            @method('PUT')
        @endif

        <div class="row">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.translatable name="title" :label="__('navigation::admin.fields.title')"
                            :value="$group->exists ? $group->getTranslations('title') : []" required />
                        <x-admin.form.input name="slug" :label="__('navigation::admin.fields.slug')" :value="$group->slug"
                            :help="__('navigation::admin.slug_help')" required />
                    </div>
                </div>
            </div>
        </div>
    </form>
</x-admin-layout::app>
