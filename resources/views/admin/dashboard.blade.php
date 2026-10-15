<x-admin-layout::app :title="__('Dashboard')" :description="__('Welcome back, :name.', ['name' => auth()->user()->name])">
    <div class="row g-4 mb-4">
        @foreach ([
            ['label' => __('Users'), 'value' => $stats['users'], 'note' => __(':count active', ['count' => $stats['activeUsers']]), 'icon' => 'fa-user-friends'],
            ['label' => __('Enabled modules'), 'value' => $stats['modulesEnabled'], 'note' => __(':count installed', ['count' => $stats['modulesInstalled']]), 'icon' => 'fa-cubes'],
            ['label' => __('Available modules'), 'value' => $stats['modulesAvailable'], 'note' => __('in the modules folder'), 'icon' => 'fa-folder-open'],
        ] as $card)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <span style="font-size:.88rem;">{{ $card['label'] }}</span>
                        <div class="sum-ico"><i class="fas {{ $card['icon'] }}"></i></div>
                    </div>
                    <div class="card-body">
                        <div class="metric">{{ number_format($card['value']) }}</div>
                        <div class="metric-sub">{{ $card['note'] }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header">
                    <span>{{ __('Recent logins') }}</span>
                    @if (Route::has('admin.users.index'))
                        <a href="{{ route('admin.users.index') }}" class="btn-ghost" style="font-size:.72rem;padding:4px 10px;">{{ __('All users') }}</a>
                    @endif
                </div>
                <div class="card-body">
                    @forelse ($recentLogins as $user)
                        <div class="act">
                            <div class="act-av c{{ $loop->index % 5 + 1 }}">
                                @if ($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="">
                                @else
                                    {{ $user->initials() }}
                                @endif
                            </div>
                            <div style="min-width:0;">
                                <div class="act-txt"><strong>{{ $user->name }}</strong> <span style="color:var(--text-muted);font-weight:400;">· {{ $user->email }}</span></div>
                                <div class="act-time">{{ $user->last_login_at?->diffForHumans() }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="mb-0" style="font-size:.85rem;color:var(--text-muted);">{{ __('No logins yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card">
                <div class="card-header">
                    <span>{{ __('System') }}</span>
                </div>
                <div class="card-body">
                    @foreach ([
                        ['label' => 'Laravel', 'value' => app()->version(), 'icon' => 'fab fa-laravel'],
                        ['label' => 'PHP', 'value' => PHP_VERSION, 'icon' => 'fab fa-php'],
                        ['label' => __('Languages'), 'value' => strtoupper(implode(' · ', (array) config('cms.locales'))), 'icon' => 'fas fa-language'],
                        ['label' => __('Theme'), 'value' => \Illuminate\Support\Str::headline((string) config('cms.theme')), 'icon' => 'fas fa-palette'],
                    ] as $row)
                        <div class="sum-row">
                            <div class="sum-ico"><i class="{{ $row['icon'] }}"></i></div>
                            <span class="sum-label">{{ $row['label'] }}</span>
                            <span class="sum-val">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-admin-layout::app>
