{{--
    The media library (odinadmin/file-manager.html): folders and storage on the left, the files of a folder
    in the middle (grid or list), the selected file's details on the right. Filters, sorting, search and
    folders are links (?folder, ?kind, ?sort, ?search, ?recent); uploading, renaming and deleting files
    run in the page (resources/js/admin/media-library.js on top of odinadmin/js/file-manager.js).
--}}
@php
    $byParent = $tree->groupBy(fn ($node) => (int) $node->parent_id);
    $here = $folder?->name ?? __('media::admin.home');
    $query = array_filter(['folder' => $folder?->id, 'recent' => $recent ? 1 : null, 'search' => $search ?: null, 'sort' => $sort !== 'date' ? $sort : null]);
    $filesTitle = match (true) {
        $search !== '' => __('media::admin.search_results', ['search' => $search]),
        $recent => __('media::admin.recent'),
        default => __('media::admin.files'),
    };
    $canUpload = auth()->user()->can('media.upload') && ! $recent && $search === '';
    $strings = [
        'uploading' => __('media::admin.uploading'),
        'uploadDone' => __('media::admin.upload_done'),
        'uploadFailed' => __('media::admin.upload_failed'),
        'tooLarge' => __('media::admin.too_large'),
        'selected' => __('media::admin.selected'),
        'filesCount' => __('media::admin.files_count'),
        'deleteConfirm' => __('media::admin.delete_confirm'),
        'deleteManyConfirm' => __('media::admin.delete_many_confirm'),
        'deleteText' => __('media::admin.delete_text'),
        'delete' => __('media::admin.delete'),
        'rename' => __('media::admin.rename'),
        'fileName' => __('media::admin.file_name'),
        'save' => __('media::admin.save'),
        'cancel' => __('media::admin.cancel'),
        'linkCopied' => __('media::admin.link_copied'),
        'error' => __('media::admin.error'),
    ];
    $folderModal = old('_modal');
@endphp

<x-admin-layout::app :title="__('media::admin.title')" :description="__('media::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('media::admin.title') => null]">

    <div class="fm" data-media-library
         @if ($canUpload) data-upload-url="{{ route('admin.media.files.store') }}" @endif
         data-folder-id="{{ $folder?->id }}"
         data-max-kb="{{ $maxKb }}"
         data-strings="{{ json_encode($strings) }}"
         @if ($folderModal && $errors->folder->any()) data-open-modal="{{ $folderModal }}" @endif>

        {{-- ============ Left: navigation and storage ============ --}}
        <aside class="fm-side">
            <div class="card card-auto">
                <div class="card-body">
                    @if (auth()->user()->can('media.upload') || auth()->user()->can('media.edit'))
                        <div class="fm-actions mb-3">
                            @can('media.upload')
                                <button type="button" class="btn-brand justify-content-center" data-fm-upload @disabled(! $canUpload)><i class="fas fa-cloud-upload-alt"></i> {{ __('media::admin.upload') }}</button>
                            @endcan
                            @can('media.edit')
                                <button type="button" class="btn-soft btn-square" title="{{ __('media::admin.new_folder') }}" aria-label="{{ __('media::admin.new_folder') }}" data-bs-toggle="modal" data-bs-target="#media-folder-modal" @disabled($recent || $search !== '')><i class="fas fa-folder-plus"></i></button>
                            @endcan
                        </div>
                        @if ($canUpload)
                            <input type="file" multiple hidden data-fm-input accept="{{ collect(\Modules\Media\Models\MediaFile::extensions())->map(fn ($ext) => '.'.$ext)->implode(',') }}">
                            <p class="tbl-sub mb-3">{{ __('media::admin.upload_hint', ['size' => \Illuminate\Support\Number::fileSize($maxKb * 1024)]) }}</p>
                        @endif
                    @endif

                    <ul class="fm-nav">
                        <li>
                            <a href="{{ route('admin.media.index') }}" class="fm-nav-link @if (! $folder && ! $recent && $search === '') active @endif">
                                <i class="far fa-folder-open"></i> {{ __('media::admin.library') }} <span class="fm-nav-count">{{ number_format($storage['count']) }}</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.media.index', ['recent' => 1]) }}" class="fm-nav-link @if ($recent) active @endif">
                                <i class="far fa-clock"></i> {{ __('media::admin.recent') }}
                            </a>
                        </li>
                    </ul>

                    @if ($tree->isNotEmpty())
                        <div class="fm-side-title">{{ __('media::admin.folders') }}</div>
                        @include('media::admin.partials.tree', ['parentId' => 0, 'current' => $folder])
                    @endif
                </div>
            </div>

            @if ($storage['total'] > 0)
                @php
                    $colours = ['image' => 'violet', 'video' => 'danger', 'doc' => 'info', 'archive' => 'dark', 'other' => 'warning'];
                @endphp
                <div class="card card-auto fm-storage">
                    <div class="card-body">
                        <div class="fm-storage-head">
                            <span class="fm-storage-used">{{ \Illuminate\Support\Number::fileSize($storage['total'], precision: 1) }}</span>
                            <span class="tbl-sub">{{ __('media::admin.storage_files', ['count' => number_format($storage['count'])]) }}</span>
                        </div>
                        <div class="fm-storage-bar" role="img" aria-label="{{ __('media::admin.storage_label') }}">
                            @foreach ($colours as $group => $colour)
                                @if ($storage['groups'][$group] > 0)
                                    <span class="{{ $colour }}" style="width: {{ round($storage['groups'][$group] / $storage['total'] * 100, 1) }}%"></span>
                                @endif
                            @endforeach
                        </div>
                        @foreach ($colours as $group => $colour)
                            @if ($storage['groups'][$group] > 0)
                                <div class="legend-row"><i style="--dot: var(--{{ $colour }})"></i><span class="legend-label">{{ __('media::admin.kinds.'.$group) }}</span><span class="legend-val">{{ \Illuminate\Support\Number::fileSize($storage['groups'][$group], precision: 1) }}</span></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        </aside>

        {{-- ============ Middle: folders and files ============ --}}
        <section class="fm-main card card-auto" aria-label="{{ __('media::admin.files') }}">
            <div class="fm-toolbar">
                <ol class="fm-crumbs" aria-label="{{ __('media::admin.folder_path') }}">
                    @if ($folder)
                        <li><a href="{{ route('admin.media.index') }}"><i class="fas fa-home"></i><span class="visually-hidden">{{ __('media::admin.home') }}</span></a></li>
                        @foreach ($folder->trail() as $step)
                            @if ($loop->last)
                                <li><span aria-current="page">{{ $step->name }}</span></li>
                            @else
                                <li><a href="{{ route('admin.media.index', ['folder' => $step->id]) }}">{{ $step->name }}</a></li>
                            @endif
                        @endforeach
                    @else
                        <li><span aria-current="page"><i class="fas fa-home me-1"></i> {{ $recent ? __('media::admin.recent') : __('media::admin.home') }}</span></li>
                    @endif
                </ol>

                @if ($folder && (auth()->user()->can('media.edit') || auth()->user()->can('media.delete')))
                    <div class="dropdown">
                        <button type="button" class="icon-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('media::admin.folder_actions') }}"><i class="fas fa-ellipsis-h"></i></button>
                        <ul class="dropdown-menu card-action-menu">
                            @can('media.edit')
                                <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#media-rename-folder-modal"><i class="fas fa-i-cursor"></i> {{ __('media::admin.rename_folder') }}</a></li>
                            @endcan
                            @can('media.delete')
                                <li><hr class="dropdown-divider mx-2"></li>
                                <li>
                                    <form method="POST" action="{{ route('admin.media.folders.destroy', $folder) }}"
                                          data-confirm="{{ __('media::admin.delete_folder_confirm', ['name' => $folder->name]) }}"
                                          data-confirm-text="{{ __('media::admin.delete_folder_hint') }}"
                                          data-confirm-button="{{ __('media::admin.delete') }}" data-cancel-button="{{ __('media::admin.cancel') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="dropdown-item danger"><i class="far fa-trash-alt"></i> {{ __('media::admin.delete_folder') }}</button>
                                    </form>
                                </li>
                            @endcan
                        </ul>
                    </div>
                @endif

                <form method="GET" action="{{ route('admin.media.index') }}" class="input-icon tt-search" role="search">
                    <i class="fas fa-search"></i>
                    <input type="search" name="search" value="{{ $search }}" class="form-control form-soft" placeholder="{{ __('media::admin.search') }}" aria-label="{{ __('media::admin.search') }}">
                </form>
                <div class="seg-control seg-sm fm-view" role="radiogroup" aria-label="{{ __('media::admin.view') }}">
                    <input class="seg-input" type="radio" name="fm-view" id="fm-view-grid" value="grid" data-fm-view checked><label class="seg-btn" for="fm-view-grid" title="{{ __('media::admin.grid') }}"><i class="fas fa-th-large"></i><span class="visually-hidden">{{ __('media::admin.grid') }}</span></label>
                    <input class="seg-input" type="radio" name="fm-view" id="fm-view-list" value="list" data-fm-view><label class="seg-btn" for="fm-view-list" title="{{ __('media::admin.list') }}"><i class="fas fa-list"></i><span class="visually-hidden">{{ __('media::admin.list') }}</span></label>
                </div>
            </div>

            {{-- Shown while files are checked --}}
            <form method="POST" action="{{ route('admin.media.files.bulk') }}" class="fm-bulk" data-media-bulk>
                @csrf
                <input type="hidden" name="action" value="">
                <strong data-fm-bulk-count></strong>
                @can('media.edit')
                    <button type="button" class="btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#media-move-modal"><i class="fas fa-arrows-alt"></i> {{ __('media::admin.move') }}</button>
                @endcan
                {{-- Always present: file-manager.js would bind its click to the whole page without it. --}}
                <button type="button" class="btn-soft btn-sm danger" data-fm-bulk-delete @cannot('media.delete') hidden disabled @endcannot><i class="far fa-trash-alt"></i> {{ __('media::admin.delete') }}</button>
                <button type="button" class="btn-ghost ms-auto" data-fm-bulk-clear>{{ __('media::admin.clear') }}</button>
            </form>

            @if ($subfolders->isNotEmpty())
                <div class="fm-section pb-0">
                    <h6 class="fm-section-title">{{ __('media::admin.folders') }}</h6>
                    <div class="fm-folders">
                        @foreach ($subfolders as $sub)
                            <a href="{{ route('admin.media.index', ['folder' => $sub->id]) }}" class="fm-folder st-progress">
                                <span class="fm-folder-ico"><i class="fas fa-folder"></i></span>
                                <span class="min-w-0">
                                    <span class="fm-folder-name">{{ $sub->name }}</span>
                                    <span class="fm-folder-meta">
                                        @if ($sub->files_count === 0 && $sub->children_count === 0)
                                            {{ __('media::admin.empty_folder') }}
                                        @else
                                            {{ collect([
                                                $sub->children_count ? trans_choice('media::admin.folders_count', $sub->children_count, ['count' => $sub->children_count]) : null,
                                                trans_choice('media::admin.files_count', $sub->files_count, ['count' => $sub->files_count]),
                                                $sub->files_count ? \Illuminate\Support\Number::fileSize((int) $sub->files_sum_size, precision: 1) : null,
                                            ])->filter()->implode(' · ') }}
                                        @endif
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="fm-filters">
                @foreach (['all' => null] + array_combine(array_keys(\Modules\Media\Models\MediaFile::GROUPS), array_keys(\Modules\Media\Models\MediaFile::GROUPS)) as $label => $value)
                    <a href="{{ route('admin.media.index', $query + array_filter(['kind' => $value])) }}" class="fm-chip @if ($kind === $value) active @endif">{{ __('media::admin.kinds.'.$label) }}</a>
                @endforeach
                <div class="dropdown">
                    <button type="button" class="btn-ghost" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: .76rem;">
                        <i class="fas fa-sort-amount-down me-1"></i> {{ __('media::admin.sort.'.$sort) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end card-action-menu">
                        @foreach (['date' => 'far fa-clock', 'name' => 'fas fa-font', 'size' => 'fas fa-weight-hanging'] as $option => $icon)
                            <li><a class="dropdown-item @if ($sort === $option) active @endif" href="{{ route('admin.media.index', array_filter(['sort' => $option !== 'date' ? $option : null, 'kind' => $kind]) + \Illuminate\Support\Arr::except($query, 'sort')) }}"><i class="{{ $icon }}"></i> {{ __('media::admin.sort.'.$option) }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="fm-section">
                <h6 class="fm-section-title">{{ $filesTitle }} <span class="tbl-sub">{{ trans_choice('media::admin.files_count', $files->total(), ['count' => number_format($files->total())]) }}</span></h6>
                <div class="fm-files">
                    @foreach ($files as $file)
                        @include('media::admin.partials.file')
                    @endforeach
                </div>
                <div class="fm-empty" @if ($files->isNotEmpty()) hidden @endif>
                    <i class="far fa-folder-open"></i>{{ $search !== '' || $kind ? __('media::admin.empty_search') : __('media::admin.empty') }}
                </div>
                @if ($files->hasPages())
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4">
                        <x-admin.pagination :paginator="$files" />
                    </div>
                @endif
            </div>

            @if ($canUpload)
                <div class="fm-drop" aria-hidden="true">
                    <i class="fas fa-cloud-upload-alt"></i>
                    {{ __('media::admin.drop', ['folder' => $here]) }}
                </div>
            @endif
        </section>

        {{-- ============ Right: details of the selected file ============ --}}
        <aside class="fm-info" data-fm-info aria-label="{{ __('media::admin.details') }}">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ __('media::admin.details') }}</span>
                    <button type="button" class="icon-btn fm-info-close" data-fm-info-close aria-label="{{ __('media::admin.close_details') }}"><i class="fas fa-times"></i></button>
                </div>
                <div class="card-body">
                    <div class="fm-empty py-4" data-media-info-empty><i class="far fa-file"></i>{{ __('media::admin.select_file') }}</div>
                    <div data-media-info-body hidden>
                        <div class="fm-preview" data-fm-field="preview"></div>
                        <h6 class="fm-info-name" data-fm-field="name"></h6>
                        <div class="fm-info-sub" data-fm-field="type"></div>
                        <dl class="details-grid">
                            <div><dt>{{ __('media::admin.size') }}</dt><dd data-fm-field="size"></dd></div>
                            <div><dt>{{ __('media::admin.dimensions') }}</dt><dd data-fm-field="dims"></dd></div>
                            <div><dt>{{ __('media::admin.uploaded') }}</dt><dd data-fm-field="date"></dd></div>
                            <div><dt>{{ __('media::admin.uploaded_by') }}</dt><dd data-fm-field="owner"></dd></div>
                            <div><dt>{{ __('media::admin.location') }}</dt><dd data-fm-field="folder"></dd></div>
                        </dl>
                        <label class="form-label" for="media-info-url">{{ __('media::admin.address') }}</label>
                        <div class="input-group mb-4">
                            <input type="text" class="form-control form-soft" id="media-info-url" readonly data-fm-field="url">
                            <button type="button" class="btn-soft btn-square" title="{{ __('media::admin.copy_link') }}" aria-label="{{ __('media::admin.copy_link') }}" data-media-copy><i class="fas fa-link"></i></button>
                        </div>
                        <div class="btn-row">
                            <a href="#" class="btn-brand" data-fm-field="download"><i class="fas fa-download"></i> {{ __('media::admin.download') }}</a>
                            @can('media.edit')
                                <button type="button" class="btn-soft btn-square" title="{{ __('media::admin.rename') }}" aria-label="{{ __('media::admin.rename') }}" data-media-rename><i class="fas fa-i-cursor"></i></button>
                            @endcan
                            @can('media.delete')
                                <button type="button" class="btn-soft btn-square danger" title="{{ __('media::admin.delete') }}" aria-label="{{ __('media::admin.delete') }}" data-fm-delete><i class="far fa-trash-alt"></i></button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    {{-- Upload progress (shown while files upload) --}}
    <div class="fm-uploads" hidden role="status" aria-live="polite">
        <div class="fm-uploads-head">
            <i class="fas fa-cloud-upload-alt"></i> <span data-fm-upload-title></span>
            <button type="button" class="icon-btn" data-fm-uploads-close aria-label="{{ __('media::admin.cancel') }}"><i class="fas fa-times"></i></button>
        </div>
        <div class="fm-uploads-list"></div>
    </div>

    @can('media.edit')
        {{-- New folder (in the current one) --}}
        <div class="modal fade" id="media-folder-modal" tabindex="-1" aria-labelledby="media-folder-title" aria-hidden="true">
            <div class="modal-dialog modal-sm modal-dialog-centered">
                <form method="POST" action="{{ route('admin.media.folders.store') }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="_modal" value="create">
                    <input type="hidden" name="parent_id" value="{{ $folder?->id }}">
                    <div class="modal-header">
                        <h5 class="modal-title" id="media-folder-title">{{ __('media::admin.new_folder') }}</h5>
                        <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="{{ __('media::admin.cancel') }}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="media-folder-name">{{ __('media::admin.folder_name') }}</label>
                        <input type="text" name="name" id="media-folder-name" maxlength="120" required
                               class="form-control form-soft @if ($folderModal === 'create' && $errors->folder->has('name')) is-invalid @endif"
                               value="{{ $folderModal === 'create' ? old('name') : '' }}" placeholder="{{ __('media::admin.folder_placeholder') }}">
                        @if ($folderModal === 'create')
                            @error('name', 'folder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @endif
                        <div class="form-text">{{ __('media::admin.folder_in', ['folder' => $here]) }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-soft" data-bs-dismiss="modal">{{ __('media::admin.cancel') }}</button>
                        <button type="submit" class="btn-brand"><i class="fas fa-folder-plus"></i> {{ __('media::admin.create') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($folder)
            <div class="modal fade" id="media-rename-folder-modal" tabindex="-1" aria-labelledby="media-rename-folder-title" aria-hidden="true">
                <div class="modal-dialog modal-sm modal-dialog-centered">
                    <form method="POST" action="{{ route('admin.media.folders.update', $folder) }}" class="modal-content">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="_modal" value="rename">
                        <div class="modal-header">
                            <h5 class="modal-title" id="media-rename-folder-title">{{ __('media::admin.rename_folder') }}</h5>
                            <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="{{ __('media::admin.cancel') }}"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="media-rename-folder-name">{{ __('media::admin.folder_name') }}</label>
                            <input type="text" name="name" id="media-rename-folder-name" maxlength="120" required
                                   class="form-control form-soft @if ($folderModal === 'rename' && $errors->folder->has('name')) is-invalid @endif"
                                   value="{{ $folderModal === 'rename' ? old('name') : $folder->name }}">
                            @if ($folderModal === 'rename')
                                @error('name', 'folder')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-soft" data-bs-dismiss="modal">{{ __('media::admin.cancel') }}</button>
                            <button type="submit" class="btn-brand">{{ __('media::admin.save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Move the checked files --}}
        <div class="modal fade" id="media-move-modal" tabindex="-1" aria-labelledby="media-move-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="media-move-title">{{ __('media::admin.move_title') }}</h5>
                        <button type="button" class="modal-close" data-bs-dismiss="modal" aria-label="{{ __('media::admin.cancel') }}"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="modal-body">
                        <x-admin.form.tree-select name="folder_id" id="media-move-folder" :label="__('media::admin.move_to')" :placeholder="__('media::admin.home')"
                            :options="$tree->map(fn ($node) => ['value' => $node->id, 'label' => $node->name, 'depth' => $node->depth, 'trail' => $node->trail])->all()" />
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-soft" data-bs-dismiss="modal">{{ __('media::admin.cancel') }}</button>
                        <button type="button" class="btn-brand" data-media-move><i class="fas fa-arrows-alt"></i> {{ __('media::admin.move') }}</button>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    @push('admin-scripts')
        <script src="{{ asset('odinadmin/js/file-manager.js') }}?ver={{ config('cms.odinadmin_version') }}"></script>
    @endpush
</x-admin-layout::app>
