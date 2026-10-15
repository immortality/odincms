@php
    $title = $user->exists ? __('users::admin.edit') : __('users::admin.new');
    $localeNames = (array) config('cms.locale_names');
    $locales = collect(config('cms.locales'))->mapWithKeys(fn ($locale) => [$locale => $localeNames[$locale] ?? strtoupper($locale)])->all();
@endphp

<x-admin-layout::app
    :title="$title"
    :description="$user->exists ? $user->email : null"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('users::admin.title') => route('admin.users.index'),
        $title => null,
    ]">

    <x-slot:actions>
        <a href="{{ route('admin.users.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('users::admin.back') }}
        </a>
    </x-slot:actions>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <span>{{ __('users::admin.account') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-admin.form.input name="name" :label="__('users::admin.fields.name')" :value="$user->name" required />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.input name="email" type="email" :label="__('users::admin.fields.email')" :value="$user->email" required />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.input name="password" type="password" autocomplete="new-password"
                                    :label="$user->exists ? __('users::admin.fields.new_password') : __('users::admin.fields.password')"
                                    :help="$user->exists ? __('users::admin.password_keep') : __('users::admin.password_empty')" />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.input name="password_confirmation" type="password" autocomplete="new-password"
                                    :label="__('users::admin.fields.password_confirmation')" />
                            </div>
                            <div class="col-12">
                                <x-admin.form.select name="groups" :label="__('users::admin.fields.groups')" :options="$groups"
                                    :value="$userGroups" multiple searchable :placeholder="__('users::admin.choose_groups')"
                                    :help="__('users::admin.groups_help')" />
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-brand"><i class="fas fa-check"></i> {{ __('users::admin.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.image name="avatar" :label="__('users::admin.fields.avatar')" :current="$user->exists ? $user->avatarUrl() : null" />
                        <x-admin.form.checkbox name="is_active" :label="__('users::admin.fields.is_active')" :checked="$user->is_active"
                            :help="__('users::admin.is_active_help')" />
                        <x-admin.form.select name="locale" :label="__('users::admin.fields.locale')" :options="$locales"
                            :value="$user->locale" :placeholder="__('users::admin.default_locale')" />
                    </div>
                </div>

                @if ($user->exists)
                    <div class="card card-auto mt-4">
                        <div class="card-body">
                            <div class="sum-row">
                                <div class="sum-ico"><i class="fas fa-sign-in-alt"></i></div>
                                <span class="sum-label">{{ __('users::admin.fields.last_login_at') }}</span>
                                <span class="sum-val">{{ $user->last_login_at?->format('d M Y H:i') ?? __('users::admin.never') }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-calendar"></i></div>
                                <span class="sum-label">{{ __('users::admin.fields.created_at') }}</span>
                                <span class="sum-val">{{ $user->created_at?->format('d M Y') }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="fas fa-shield-alt"></i></div>
                                <span class="sum-label">{{ __('users::admin.two_factor') }}</span>
                                <span class="sum-val">{{ $user->hasEnabledTwoFactorAuthentication() ? __('users::admin.on') : __('users::admin.off') }}</span>
                            </div>
                            <button type="submit" form="reset-link-form" class="btn-soft btn-block mt-3">
                                <i class="far fa-envelope"></i> {{ __('users::admin.send_reset_link') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>

    @if ($user->exists)
        <form id="reset-link-form" method="POST" action="{{ route('admin.users.reset-link', $user) }}" class="d-none">
            @csrf
        </form>

        @if (! $user->is(auth()->user()) && auth()->user()->can('delete', $user))
            <div class="mt-4">
                <x-admin.confirm-delete :action="route('admin.users.destroy', $user)"
                    :message="__('users::admin.delete_confirm', ['name' => $user->name])" />
            </div>
        @endif
    @endif
</x-admin-layout::app>
