@php
    $localeNames = (array) config('cms.locale_names');
    $locales = collect(config('cms.locales'))->mapWithKeys(fn ($locale) => [$locale => $localeNames[$locale] ?? strtoupper($locale)])->all();
    $twoFactorPending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
    $twoFactorOn = $user->hasEnabledTwoFactorAuthentication();
    $codeError = $errors->confirmTwoFactorAuthentication->first('code');
@endphp

<x-admin-layout::app
    :title="__('users::admin.profile')"
    :description="$user->email"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('users::admin.profile') => null]">

    <div class="row g-4">
        {{-- Details --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <span>{{ __('users::admin.profile_details') }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <x-admin.form.input name="name" :label="__('users::admin.fields.name')" :value="$user->name" required />
                        <x-admin.form.input name="email" type="email" :label="__('users::admin.fields.email')" :value="$user->email"
                            :help="__('users::admin.email_reverify')" required />
                        <x-admin.form.select name="locale" :label="__('users::admin.fields.locale')" :options="$locales"
                            :value="$user->locale" :placeholder="__('users::admin.default_locale')" />
                        <x-admin.form.image name="avatar" :label="__('users::admin.fields.avatar')" :current="$user->avatarUrl()" />
                        <button type="submit" class="btn-brand"><i class="fas fa-check"></i> {{ __('users::admin.save') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Password --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <span>{{ __('users::admin.change_password') }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.profile.password') }}">
                        @csrf
                        @method('PUT')
                        <x-admin.form.input name="current_password" type="password" autocomplete="current-password"
                            :label="__('users::admin.fields.current_password')" required />
                        <x-admin.form.input name="password" type="password" autocomplete="new-password"
                            :label="__('users::admin.fields.new_password')" required />
                        <x-admin.form.input name="password_confirmation" type="password" autocomplete="new-password"
                            :label="__('users::admin.fields.password_confirmation')" required />
                        <button type="submit" class="btn-brand"><i class="fas fa-key"></i> {{ __('users::admin.change_password') }}</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Two-factor authentication (Fortify routes) --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <span>{{ __('users::admin.two_factor') }}</span>
                    <span @class(['badge-status', 'success' => $twoFactorOn, 'warning' => $twoFactorPending, 'muted' => ! $twoFactorOn && ! $twoFactorPending])>
                        {{ $twoFactorOn ? __('users::admin.on') : ($twoFactorPending ? __('users::admin.two_factor_pending') : __('users::admin.off')) }}
                    </span>
                </div>
                <div class="card-body">
                    <p style="font-size:.85rem;color:var(--text-muted);">{{ __('users::admin.two_factor_help') }}</p>

                    @if (! $twoFactorOn && ! $twoFactorPending)
                        <form method="POST" action="{{ route('two-factor.enable') }}">
                            @csrf
                            <button type="submit" class="btn-brand"><i class="fas fa-shield-alt"></i> {{ __('users::admin.two_factor_enable') }}</button>
                        </form>
                    @endif

                    @if ($twoFactorPending)
                        <div class="row g-4 align-items-center">
                            <div class="col-md-auto">
                                <div class="p-2 bg-white border rounded d-inline-block">{!! $user->twoFactorQrCodeSvg() !!}</div>
                            </div>
                            <div class="col-md">
                                <p>{{ __('users::admin.two_factor_scan') }}</p>
                                <p style="font-size:.8rem;color:var(--text-muted);">{{ __('users::admin.two_factor_key') }} <code>{{ decrypt($user->two_factor_secret) }}</code></p>
                                <form method="POST" action="{{ route('two-factor.confirm') }}" class="d-flex flex-wrap gap-2 align-items-start">
                                    @csrf
                                    <div>
                                        <input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456"
                                            aria-label="{{ __('Code') }}" @class(['form-control form-soft', 'is-invalid' => $codeError])>
                                        @if ($codeError)
                                            <div class="invalid-feedback d-block">{{ $codeError }}</div>
                                        @endif
                                    </div>
                                    <button type="submit" class="btn-brand">{{ __('users::admin.two_factor_confirm') }}</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($twoFactorOn)
                        <button type="button" class="btn-soft" data-bs-toggle="collapse" data-bs-target="#recovery-codes" aria-expanded="false" aria-controls="recovery-codes">
                            <i class="fas fa-list"></i> {{ __('users::admin.recovery_codes') }}
                        </button>
                        <div class="collapse" id="recovery-codes">
                            <div class="pt-3">
                                <p style="font-size:.8rem;color:var(--text-muted);">{{ __('users::admin.recovery_codes_help') }}</p>
                                <pre class="p-3 rounded border" style="background:var(--neutral-soft);">{{ implode("\n", $user->recoveryCodes()) }}</pre>
                                <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn-soft btn-sm"><i class="fas fa-sync-alt"></i> {{ __('users::admin.recovery_codes_new') }}</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($twoFactorOn || $twoFactorPending)
                        <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-3"
                              data-confirm="{{ __('users::admin.two_factor_disable_confirm') }}" data-confirm-button="{{ __('users::admin.two_factor_disable') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-soft danger"><i class="fas fa-times"></i> {{ __('users::admin.two_factor_disable') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout::app>
