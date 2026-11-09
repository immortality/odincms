{{-- One file card / list row (odinadmin/file-manager.html → .fm-file). The data-* attributes fill the details panel. --}}
@php
    $kind = $file->kind();
    $owner = $file->uploader?->name ?? '—';
    $date = $file->created_at?->translatedFormat('j M Y') ?? '';
    $folderName = $file->folder?->name ?? __('media::admin.home');
@endphp
<div class="fm-file {{ $file->tone() }}" tabindex="0"
     data-id="{{ $file->id }}"
     data-name="{{ $file->name }}"
     data-kind="{{ $kind }}"
     data-ext="{{ $file->extension }}"
     data-icon="{{ $file->icon() }}"
     data-tone="{{ $file->tone() }}"
     data-type="{{ __('media::admin.types.'.$kind) }} · {{ strtoupper($file->extension) }}"
     data-size-label="{{ $file->humanSize() }}"
     data-dims="{{ $file->dimensions() ?? '—' }}"
     data-date="{{ $date }}"
     data-owner="{{ $owner }}"
     data-folder="{{ $folderName }}"
     data-url="{{ $file->url() }}"
     data-download="{{ route('admin.media.files.download', $file) }}"
     data-update="{{ route('admin.media.files.update', $file) }}"
     data-delete="{{ route('admin.media.files.destroy', $file) }}"
     @if ($file->isImage()) data-src="{{ $file->url() }}" @endif>
    <label class="fm-check"><input type="checkbox" class="form-check-input check-soft" value="{{ $file->id }}" aria-label="{{ __('media::admin.select', ['name' => $file->name]) }}"></label>
    <div class="fm-thumb">
        @if ($file->isImage())
            <img src="{{ $file->url() }}" alt="" loading="lazy">
        @else
            <i class="{{ $file->icon() }}"></i>
        @endif
        <span class="fm-ext">{{ $file->extension }}</span>
    </div>
    <div class="fm-file-body">
        <div class="fm-file-text"><span class="fm-name">{{ $file->name }}</span><span class="fm-meta">{{ $file->humanSize() }} · {{ $date }}</span></div>
        <span class="fm-col fm-col-owner">{{ $owner }}</span>
        <span class="fm-col fm-col-date">{{ $date }}</span>
        <span class="fm-col fm-col-size">{{ $file->humanSize() }}</span>
        <div class="dropdown">
            <button type="button" class="icon-btn" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="{{ __('media::admin.file_actions') }}"><i class="fas fa-ellipsis-v"></i></button>
            <ul class="dropdown-menu dropdown-menu-end card-action-menu">
                <li><a class="dropdown-item" href="{{ $file->url() }}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> {{ __('media::admin.open') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('admin.media.files.download', $file) }}"><i class="fas fa-download"></i> {{ __('media::admin.download') }}</a></li>
                <li><a class="dropdown-item" href="#" data-media-copy><i class="fas fa-link"></i> {{ __('media::admin.copy_link') }}</a></li>
                @can('media.edit')
                    <li><a class="dropdown-item" href="#" data-media-rename><i class="fas fa-i-cursor"></i> {{ __('media::admin.rename') }}</a></li>
                @endcan
                @can('media.delete')
                    <li><hr class="dropdown-divider mx-2"></li>
                    <li><a class="dropdown-item danger" href="#" data-fm-delete><i class="far fa-trash-alt"></i> {{ __('media::admin.delete') }}</a></li>
                @endcan
            </ul>
        </div>
    </div>
</div>
