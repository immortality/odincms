@php
    $user = auth()->user();
    $role = $user?->getRoleNames()->first();
    $avatar = $user?->avatarUrl();

    $locales = (array) config('cms.locales');
    $names = (array) config('cms.locale_names');
    $current = app()->getLocale();
@endphp

<header class="topbar">
    <button class="burger" id="burger" type="button" aria-label="{{ __('Toggle menu') }}">
        <i class="fas fa-bars"></i>
    </button>

    <div class="tb-actions">
        <a href="{{ url('/') }}" target="_blank" class="tb-btn" title="{{ __('View site') }}" aria-label="{{ __('View site') }}">
            <i class="fas fa-globe"></i>
        </a>

        @if (count($locales) > 1)
            <div class="dropdown">
                <button class="tb-lang" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Language') }}">
                    <span class="lang-code">{{ strtoupper($current) }}</span>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end lang-menu">
                    <li><h6 class="dropdown-header">{{ __('Select language') }}</h6></li>
                    @foreach ($locales as $locale)
                        <li>
                            <a @class(['dropdown-item', 'active' => $locale === $current]) href="{{ request()->fullUrlWithQuery(['locale' => $locale]) }}" lang="{{ $locale }}">
                                <span class="lang-code">{{ strtoupper($locale) }}</span>
                                <span>{{ $names[$locale] ?? strtoupper($locale) }}</span>
                                <i class="fas fa-check lang-check"></i>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($user)
            <div class="dropdown">
                <button class="tb-profile" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="av">
                        @if ($avatar)
                            <img src="{{ $avatar }}" alt="">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </div>
                    <div class="p-meta">
                        <span class="p-name">{{ $user->name }}</span>
                        <span class="p-role">{{ $role ? \Illuminate\Support\Str::headline($role) : '' }}</span>
                    </div>
                    <i class="fas fa-chevron-down chev"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end profile-menu">
                    <li>
                        <div class="profile-head">
                            <div class="av">
                                @if ($avatar)
                                    <img src="{{ $avatar }}" alt="">
                                @else
                                    {{ $user->initials() }}
                                @endif
                            </div>
                            <div>
                                <div class="p-name">{{ $user->name }}</div>
                                <div class="p-email">{{ $user->email }}</div>
                            </div>
                        </div>
                    </li>
                    @if (Route::has('admin.users.profile'))
                        <li><a class="dropdown-item" href="{{ route('admin.users.profile') }}"><i class="ic far fa-user"></i> {{ __('My profile') }}</a></li>
                    @endif
                    @if (Route::has('logout'))
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item danger">
                                    <i class="ic fas fa-sign-out-alt"></i> {{ __('Sign out') }}
                                </button>
                            </form>
                        </li>
                    @endif
                </ul>
            </div>
        @endif
    </div>
</header>
