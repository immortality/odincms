{{-- One row of the page tree table (admin/index). $page, $depth, $children (number of subpages), $canEdit, $layouts --}}
@php
    $label = $page->label();
@endphp

<tr data-id="{{ $page->id }}" data-parent="{{ $page->parent_id }}" data-depth="{{ $depth }}" data-sort="{{ $page->sort_order }}" data-status="{{ $page->status }}">
    @if ($canEdit)
        <td class="col-handle">
            <span class="row-handle" title="{{ __('pages::admin.drag') }}" aria-label="{{ __('pages::admin.drag') }}"><i class="fas fa-grip-vertical"></i></span>
        </td>
    @endif
    <td><span class="tbl-id">#{{ $page->id }}</span></td>
    <td>
        <div class="tree-cell" style="--depth:{{ $depth }}">
            {{-- tree-table.js sets the label and hides the toggle of rows without subpages --}}
            <button type="button" @class(['tree-toggle', 'is-empty' => ! $children]) data-tree-toggle aria-expanded="true"
                    aria-label="{{ __('pages::admin.tree.collapse') }}" @unless ($children) tabindex="-1" @endunless><i class="fas fa-chevron-right"></i></button>
            <div>
                @if ($canEdit)
                    <a href="{{ route('admin.pages.edit', $page) }}" class="tbl-title">{{ $label }}</a>
                @else
                    <span class="tbl-title">{{ $label }}</span>
                @endif
                <span class="tbl-sub">/{{ $page->path }}</span>
            </div>
        </div>
    </td>
    <td>{{ $layouts[$page->layout] ?? $page->layout }}</td>
    <td>
        <span @class(['badge-status', 'success' => $page->isPublished(), 'muted' => ! $page->isPublished()])>{{ __('pages::admin.statuses.'.$page->status) }}</span>
    </td>
    <td class="tbl-date">
        {{ $page->updated_at?->translatedFormat('d M Y') }}<span class="tbl-sub">{{ $page->updated_at?->format('H:i') }}</span>
    </td>
    <td class="col-actions">
        <div class="tbl-actions">
            <a href="{{ $page->url() }}" target="_blank" rel="noopener" class="icon-btn"
               title="{{ $page->isPublished() ? __('pages::admin.view') : __('pages::admin.preview') }}"
               aria-label="{{ ($page->isPublished() ? __('pages::admin.view') : __('pages::admin.preview')).': '.$label }}"><i class="far fa-eye"></i></a>
            @can('pages.create')
                <a href="{{ route('admin.pages.create', ['parent' => $page->id]) }}" class="icon-btn"
                   title="{{ __('pages::admin.add_child') }}" aria-label="{{ __('pages::admin.add_child').': '.$label }}"><i class="fas fa-plus"></i></a>
            @endcan
            @if ($canEdit)
                <a href="{{ route('admin.pages.edit', $page) }}" class="icon-btn"
                   title="{{ __('Edit') }}" aria-label="{{ __('Edit').': '.$label }}"><i class="fas fa-pen"></i></a>
            @endif
            @can('pages.delete')
                @if ($children)
                    {{-- A page with subpages cannot be deleted (PageController::destroy) --}}
                    <button type="button" class="icon-btn danger" disabled
                            title="{{ __('pages::admin.errors.has_children') }}" aria-label="{{ __('Delete').': '.$label }}"><i class="far fa-trash-alt"></i></button>
                @else
                    <x-admin.confirm-delete :action="route('admin.pages.destroy', $page)" icon-only
                        :message="__('pages::admin.delete_confirm', ['title' => $label])" />
                @endif
            @endcan
        </div>
    </td>
</tr>
