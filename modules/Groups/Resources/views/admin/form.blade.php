@php
    $title = $group->exists ? __('groups::admin.edit') : __('groups::admin.new');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('groups::admin.title') => route('admin.groups.index'),
        $title => null,
    ]">

    <x-slot:actions>
        <a href="{{ route('admin.groups.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('groups::admin.back') }}
        </a>
    </x-slot:actions>

    <form method="POST" action="{{ $group->exists ? route('admin.groups.update', $group) : route('admin.groups.store') }}">
        @csrf
        @if ($group->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <x-admin.form.translatable
                            name="title"
                            :label="__('groups::admin.fields.title')"
                            :value="$group->exists ? $group->getTranslations('title') : []"
                            required />

                        @if ($group->exists && $group->isProtected())
                            <div class="mb-3">
                                <label class="form-label" for="field-name">{{ __('groups::admin.fields.name') }}</label>
                                <input type="text" id="field-name" class="form-control form-soft" value="{{ $group->name }}" disabled>
                                <div class="form-text">{{ __('groups::admin.name_protected') }}</div>
                            </div>
                        @else
                            <x-admin.form.input name="name" :label="__('groups::admin.fields.name')" :value="$group->name"
                                :help="__('groups::admin.name_help')" required />
                        @endif

                        <div class="form-actions">
                            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> {{ __('groups::admin.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <div class="d-flex gap-3">
                            <div class="sum-ico"><i class="fas fa-shield-alt"></i></div>
                            <div>
                                <p class="mb-2" style="font-size:.85rem;color:var(--text-muted);">{{ __('groups::admin.permissions_hint') }}</p>
                                @if (Route::has('admin.permissions.index'))
                                    @can('permissions.view')
                                        <a href="{{ route('admin.permissions.index') }}" class="btn-soft btn-sm">
                                            {{ __('groups::admin.open_permissions') }} <i class="fas fa-arrow-right"></i>
                                        </a>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Outside the form above: forms cannot be nested. --}}
    @if ($group->exists && ! $group->isProtected() && auth()->user()->can('delete', $group))
        <div class="row">
            <div class="col-lg-4 offset-lg-8 mt-4">
                <x-admin.confirm-delete :action="route('admin.groups.destroy', $group)"
                    :text="__('groups::admin.delete_text', ['count' => $group->users()->count()])" />
            </div>
        </div>
    @endif
</x-admin-layout::app>
