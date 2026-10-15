<x-admin-layout::app
    :title="__('blog::admin.title')"
    :description="__('blog::admin.description', ['count' => $posts->total()])"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('blog::admin.title') => null]">

    @can('create', \Modules\Blog\Models\Post::class)
        <x-slot:actions>
            @can('categories.view')
                <a href="{{ route('admin.categories.tree', 'blog') }}" class="btn-soft">
                    <i class="fas fa-folder-tree"></i> {{ __('blog::admin.categories') }}
                </a>
            @endcan
            <a href="{{ route('admin.blog.create') }}" class="btn-brand">
                <i class="fas fa-plus"></i> {{ __('blog::admin.create') }}
            </a>
        </x-slot:actions>
    @endcan

    <x-admin.table bulk-form="bulk-form" :empty="__('blog::admin.empty')">
        <x-slot:toolbar>
            <x-admin.filters :placeholder="__('blog::admin.search')">
                <select name="status" class="form-select form-soft tt-select" aria-label="{{ __('blog::admin.fields.status') }}">
                    <option value="">{{ __('blog::admin.all_statuses') }}</option>
                    @foreach (__('blog::admin.filter_statuses') as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($categories !== [])
                    <select name="category" class="form-select form-soft tt-select" aria-label="{{ __('blog::admin.fields.category') }}">
                        <option value="">{{ __('blog::admin.all_categories') }}</option>
                        @foreach ($categories as $value => $label)
                            <option value="{{ $value }}" @selected((int) request('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="check-row mb-0">
                    <input type="checkbox" class="form-check-input check-soft" name="mine" value="1" @checked(request()->boolean('mine'))>
                    {{ __('blog::admin.mine') }}
                </label>
            </x-admin.filters>
        </x-slot:toolbar>

        <x-slot:bulk>
            <x-admin.bulk-actions :action="route('admin.blog.bulk')" :actions="__('blog::admin.actions')" />
        </x-slot:bulk>

        <x-slot:head>
            <th>{{ __('blog::admin.fields.title') }}</th>
            <th>{{ __('blog::admin.fields.category') }}</th>
            <th>{{ __('blog::admin.fields.status') }}</th>
            <th>{{ __('blog::admin.fields.author') }}</th>
            <th>{{ __('blog::admin.fields.published_at') }}</th>
            <th class="col-actions"><span class="visually-hidden">{{ __('Actions') }}</span></th>
        </x-slot:head>

        @foreach ($posts as $post)
            @php($state = $post->isScheduled() ? 'scheduled' : $post->status)
            <tr>
                <x-admin.table.check :value="$post->id" :label="$post->title" />
                <td class="col-main" data-label="{{ __('blog::admin.fields.title') }}">
                    <div class="tbl-item">
                        @if ($cover = $post->coverUrl())
                            <img src="{{ $cover }}" alt="" class="tbl-thumb" loading="lazy">
                        @else
                            <div class="tbl-thumb d-grid" style="place-items:center;color:var(--text-muted);"><i class="far fa-newspaper"></i></div>
                        @endif
                        <div>
                            @can('update', $post)
                                <a href="{{ route('admin.blog.edit', $post) }}" class="tbl-title">{{ $post->title }}</a>
                            @else
                                <span class="tbl-title">{{ $post->title }}</span>
                            @endcan
                            <span class="tbl-sub">/blog/{{ $post->slug }}</span>
                        </div>
                    </div>
                </td>
                <td data-label="{{ __('blog::admin.fields.category') }}">{{ $post->category?->label() ?? '—' }}</td>
                <td data-label="{{ __('blog::admin.fields.status') }}">
                    <span @class(['badge-status', 'success' => $state === 'published', 'info' => $state === 'scheduled', 'muted' => $state === 'draft'])>
                        {{ __('blog::admin.filter_statuses.'.$state) }}
                    </span>
                </td>
                <td data-label="{{ __('blog::admin.fields.author') }}">{{ $post->author?->name ?? '—' }}</td>
                <td data-label="{{ __('blog::admin.fields.published_at') }}" class="tbl-date">
                    @if ($post->published_at)
                        {{ $post->published_at->format('d.m.Y') }}<span class="tbl-sub">{{ $post->published_at->format('H:i') }}</span>
                    @else
                        —
                    @endif
                </td>
                <td class="col-actions" data-label="{{ __('Actions') }}">
                    <div class="tbl-actions">
                        <a href="{{ $post->url() }}" target="_blank" rel="noopener" class="icon-btn" title="{{ __('blog::admin.view') }}" aria-label="{{ __('blog::admin.view') }}">
                            <i class="far fa-eye"></i>
                        </a>
                        @can('update', $post)
                            <a href="{{ route('admin.blog.edit', $post) }}" class="icon-btn" title="{{ __('blog::admin.edit') }}" aria-label="{{ __('blog::admin.edit') }}">
                                <i class="fas fa-pen"></i>
                            </a>
                        @endcan
                        @can('delete', $post)
                            <x-admin.confirm-delete :action="route('admin.blog.destroy', $post)" icon-only
                                :message="__('blog::admin.delete_confirm', ['title' => $post->title])" />
                        @endcan
                    </div>
                </td>
            </tr>
        @endforeach

        <x-slot:footer>
            <x-admin.pagination :paginator="$posts" />
        </x-slot:footer>
    </x-admin.table>
</x-admin-layout::app>
