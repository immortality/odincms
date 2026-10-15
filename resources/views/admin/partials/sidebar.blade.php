@php
    /*
     * $adminMenu comes from App\Cms\Admin\AdminMenu through a view composer (CmsServiceProvider):
     * [ ['label' => 'Content', 'items' => [ ['label', 'url', 'icon', 'active', 'children' => [...]] ]] ]
     */
    $menu = $adminMenu ?? [];
    $user = auth()->user();
    $role = $user?->getRoleNames()->first();
    $avatar = $user?->avatarUrl();

    // Font Awesome classes from the manifests (older "ni-…" names are mapped by App\Cms\Admin\AdminIcon).
    $icon = fn (?string $name) => \App\Cms\Admin\AdminIcon::fa($name);
@endphp

<aside class="sidebar" id="sidebar">
    <a href="{{ url(config('cms.admin_prefix')) }}" class="sb-brand">
        <div class="logo"><i class="fas fa-bolt"></i></div>
        <span>{{ config('app.name') }}</span>
    </a>

    <div class="sb-scroll">
        <ul class="sb-nav">
            @foreach ($menu as $group)
                @if (! empty($group['label']))
                    <li class="sb-section">
                        <span class="sb-section-dot"></span>
                        {{ $group['label'] }}
                    </li>
                @endif

                @foreach ($group['items'] as $item)
                    @php
                        $children = $item['children'] ?? [];
                        $active = (bool) ($item['active'] ?? false);
                        $subId = 'sb-sub-'.$loop->parent->index.'-'.$loop->index;
                    @endphp
                    <li class="nav-item">
                        @if ($children)
                            <a href="#" @class(['nav-link', 'active open' => $active]) data-toggle="submenu" data-target="#{{ $subId }}">
                                <i class="nav-ico {{ $icon($item['icon'] ?? null) }}"></i>
                                <span>{{ $item['label'] }}</span>
                                <i class="nav-arrow fas fa-chevron-right"></i>
                            </a>
                            <div @class(['sb-subwrap', 'open' => $active]) id="{{ $subId }}">
                                <div class="sb-subinner">
                                    <ul class="sb-subnav">
                                        @foreach ($children as $child)
                                            <li><a href="{{ $child['url'] }}" @class(['nav-link', 'active' => $child['active'] ?? false])>{{ $child['label'] }}</a></li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @else
                            <a href="{{ $item['url'] }}" @class(['nav-link', 'active' => $active])>
                                <i class="nav-ico {{ $icon($item['icon'] ?? null) }}"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>

    @if ($user)
        <div class="sb-user-wrap">
            <a @if (Route::has('admin.users.profile')) href="{{ route('admin.users.profile') }}" @endif class="sb-user text-decoration-none">
                <div class="av">
                    @if ($avatar)
                        <img src="{{ $avatar }}" alt="">
                    @else
                        {{ $user->initials() }}
                    @endif
                </div>
                <div>
                    <div class="u-name">{{ $user->name }}</div>
                    <div class="u-role">{{ $role ? \Illuminate\Support\Str::headline($role) : '' }}</div>
                </div>
            </a>
        </div>
    @endif
</aside>

<div class="backdrop" id="backdrop"></div>
