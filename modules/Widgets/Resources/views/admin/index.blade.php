<x-admin-layout::app
    :title="__('widgets::admin.title')"
    :description="__('widgets::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('widgets::admin.title') => null]">

    <x-slot:actions>
        <a href="{{ route('admin.widgets.areas.index') }}" class="btn-soft">
            <i class="fas fa-th-large"></i> {{ __('widgets::admin.manage_areas') }}
        </a>
    </x-slot:actions>

    <div class="row g-4">
        {{-- Areas: one card each; widgets are template drag items, sortable within and between areas --}}
        <div class="col-xl-9">
            @if ($areas->isEmpty())
                <div class="card card-auto">
                    <div class="card-body text-center py-5" style="color:var(--text-muted);font-size:.85rem;">
                        <i class="fas fa-th-large d-block mb-2" style="font-size:1.6rem;color:var(--border-strong);"></i>
                        {{ __('widgets::admin.no_areas') }}
                    </div>
                </div>
            @endif

            <div class="row g-4" id="widget-board">
                @foreach ($areas as $area)
                    <div class="col-md-6 col-xxl-4">
                        <div class="card">
                            <div class="card-header">
                                <span>{{ $area->name }}</span>
                                <span class="code-tag">@@widgetArea('{{ $area->slug }}')</span>
                            </div>
                            <div class="card-body">
                                @if ($area->instances->isEmpty())
                                    {{-- No whitespace inside, so the template's .drag-list:empty "drop here" box shows --}}
                                    <div class="drag-list widget-list" data-area="{{ $area->id }}" data-empty="{{ __('widgets::admin.drop_here') }}"></div>
                                @else
                                <div class="drag-list widget-list" data-area="{{ $area->id }}" data-empty="{{ __('widgets::admin.drop_here') }}">
                                    @foreach ($area->instances as $instance)
                                        @php($type = $registry->get($instance->widget))
                                        <div class="drag-item has-handle widget-card" data-id="{{ $instance->id }}">
                                            @can('widgets.edit')
                                                <span class="drag-handle sortable-handle"><i class="fas fa-grip-vertical"></i></span>
                                            @endcan
                                            <div class="drag-item-body">
                                                <span class="drag-item-title">
                                                    {{ $instance->title ?: ($type ? $type::label() : $instance->widget) }}
                                                    @unless ($instance->is_active)
                                                        <span class="badge-soft warning ms-1">{{ __('widgets::admin.inactive') }}</span>
                                                    @endunless
                                                </span>
                                                @if ($type === null)
                                                    <span class="drag-item-sub" style="color:var(--danger);">{{ __('widgets::admin.unavailable') }}</span>
                                                @else
                                                    <span class="drag-item-sub">{{ $type::label() }}</span>
                                                @endif
                                            </div>
                                            <div class="tbl-actions">
                                                @if ($type !== null)
                                                    @can('widgets.edit')
                                                        <a href="{{ route('admin.widgets.edit', $instance) }}" class="icon-btn" title="{{ __('widgets::admin.edit') }}" aria-label="{{ __('widgets::admin.edit') }}"><i class="fas fa-pen"></i></a>
                                                    @endcan
                                                @endif
                                                @can('widgets.delete')
                                                    <x-admin.confirm-delete :action="route('admin.widgets.destroy', $instance)" icon-only />
                                                @endcan
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @can('widgets.edit')
                @if ($areas->isNotEmpty())
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-4">
                        <button type="button" id="widget-board-save" class="btn-brand" disabled><i class="fas fa-check"></i> {{ __('widgets::admin.save_order') }}</button>
                        <span class="table-info">{{ __('widgets::admin.drag_hint') }}</span>
                    </div>
                @endif
            @endcan
        </div>

        {{-- Available widget types --}}
        <div class="col-xl-3">
            <div class="card card-auto">
                <div class="card-header"><span>{{ __('widgets::admin.available') }}</span></div>
                <div class="card-body">
                    @foreach ($types as $key => $class)
                        @can('widgets.create')
                            <a href="{{ route('admin.widgets.create', ['widget' => $key]) }}" class="sum-row text-decoration-none">
                        @else
                            <div class="sum-row">
                        @endcan
                            <div class="sum-ico"><i class="{{ \App\Cms\Admin\AdminIcon::fa($class::$icon) }}"></i></div>
                            <span class="sum-label">
                                <span style="color:var(--text-dark);font-weight:600;">{{ $class::label() }}</span>
                                @if ($class::$description)
                                    <span class="d-block" style="font-size:.74rem;color:var(--text-muted);">{{ __($class::$description) }}</span>
                                @endif
                            </span>
                            @can('widgets.create')
                                <i class="fas fa-plus" style="color:var(--primary);font-size:.8rem;"></i>
                            @endcan
                        @can('widgets.create')
                            </a>
                        @else
                            </div>
                        @endcan
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @can('widgets.edit')
        @push('admin-scripts')
            <script type="module">
                const board = document.getElementById('widget-board');
                const save = document.getElementById('widget-board-save');
                const toast = (text, type) => window.Odin?.toast?.({ type, text });

                if (board && save && window.Sortable) {
                    board.querySelectorAll('.widget-list').forEach((list) => window.Sortable.create(list, {
                        group: 'widgets',
                        handle: '.sortable-handle',
                        animation: 150,
                        onEnd: () => { save.disabled = false; },
                    }));

                    save.addEventListener('click', () => {
                        const areas = {};
                        board.querySelectorAll('.widget-list').forEach((list) => {
                            areas[list.dataset.area] = [...list.querySelectorAll('.widget-card')].map((card) => Number(card.dataset.id));
                        });

                        save.disabled = true;
                        fetch(@js(route('admin.widgets.reorder')), {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ areas }),
                        })
                            .then(async (response) => {
                                const data = await response.json().catch(() => ({}));
                                if (!response.ok) throw new Error(data.message || response.statusText);
                                toast(data.message, 'success');
                            })
                            .catch((error) => {
                                toast(error.message, 'danger');
                                save.disabled = false;
                            });
                    });
                }
            </script>
        @endpush
    @endcan
</x-admin-layout::app>
