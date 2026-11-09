<?php

namespace Modules\Media\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Media\Models\MediaFile;
use Modules\Media\Models\MediaFolder;
use Modules\Media\Support\Uploader;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    public const SORTS = ['date', 'name', 'size'];

    /**
     * The library: a folder (or the top level) with its subfolders and files, "Recent uploads" across all
     * folders, or search results across all folders. ?kind= and ?sort= filter and order the files.
     */
    public function index(Request $request): View
    {
        Gate::authorize('media.view');

        $search = trim($request->string('search')->toString());
        $recent = $request->boolean('recent') && $search === '';
        $folder = $search === '' && ! $recent && $request->filled('folder')
            ? MediaFolder::findOrFail($request->integer('folder'))
            : null;
        $kind = array_key_exists($request->string('kind')->toString(), MediaFile::GROUPS) ? $request->string('kind')->toString() : null;
        $sort = in_array($request->string('sort')->toString(), self::SORTS, true) ? $request->string('sort')->toString() : 'date';

        $files = MediaFile::query()
            ->with('uploader:id,name', 'folder:id,name')
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($search === '' && ! $recent, fn (Builder $query) => $query->where('folder_id', $folder?->id))
            ->when($kind, fn (Builder $query, string $kind) => $query->ofGroup($kind))
            ->when($sort === 'name', fn (Builder $query) => $query->orderBy('name'))
            ->when($sort === 'size', fn (Builder $query) => $query->orderByDesc('size'))
            ->latest('id')
            ->paginate(60)
            ->withQueryString();

        $subfolders = $search === '' && ! $recent
            ? MediaFolder::where('parent_id', $folder?->id)->withCount(['files', 'children'])->withSum('files', 'size')->orderBy('name')->get()
            : collect();

        return view('media::admin.index', [
            'folder' => $folder,
            'subfolders' => $subfolders,
            'files' => $files,
            'tree' => MediaFolder::tree(),
            'openIds' => $folder ? array_map(fn (MediaFolder $f) => $f->id, $folder->trail()) : [],
            'search' => $search,
            'recent' => $recent,
            'kind' => $kind,
            'sort' => $sort,
            'storage' => $this->storage(),
            'maxKb' => MediaFile::maxUploadKb(),
        ]);
    }

    /**
     * One file per request (the page sends them one after another, with progress). Answers with the
     * file's card, so the page can add it without reloading.
     */
    public function store(Request $request, Uploader $uploader): JsonResponse
    {
        Gate::authorize('media.upload');

        $request->validate([
            'file' => Uploader::rules(),
            'folder_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
        ], [
            'file.extensions' => __('media::admin.type_not_allowed'),
            'file.mimes' => __('media::admin.type_not_allowed'),
        ]);

        $file = $uploader->store(
            $request->file('file'),
            $request->filled('folder_id') ? MediaFolder::find($request->integer('folder_id')) : null,
            $request->user()?->id,
        );

        return response()->json([
            'id' => $file->id,
            'html' => view('media::admin.partials.file', ['file' => $file->load('uploader:id,name')])->render(),
        ], 201);
    }

    /**
     * Rename a file. The extension stays; the stored file and its address do not change.
     */
    public function update(Request $request, MediaFile $file): JsonResponse
    {
        Gate::authorize('media.edit');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:180', 'not_regex:/[\x00-\x1F\x7F\/\\\\]/'],
        ]);

        $stem = trim((string) preg_replace('/\.'.preg_quote($file->extension, '/').'$/i', '', trim($data['name'])));
        $file->update(['name' => ($stem !== '' ? $stem : 'file').'.'.$file->extension]);

        return response()->json(['name' => $file->name, 'message' => __('media::admin.renamed')]);
    }

    public function destroy(Request $request, MediaFile $file): JsonResponse|RedirectResponse
    {
        Gate::authorize('media.delete');

        $file->delete();

        return $request->expectsJson()
            ? response()->json(['message' => __('media::admin.file_deleted')])
            : back()->with('success', __('media::admin.file_deleted'));
    }

    public function download(MediaFile $file): StreamedResponse
    {
        Gate::authorize('media.view');

        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    /**
     * Move the checked files to a folder, or delete them.
     */
    public function bulk(Request $request): RedirectResponse
    {
        abort_unless(Gate::any(['media.edit', 'media.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['move', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'folder_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
        ]);

        $action = $request->string('action')->toString();
        Gate::authorize($action === 'delete' ? 'media.delete' : 'media.edit');

        $files = MediaFile::whereKey($request->array('ids'))->get();

        DB::transaction(function () use ($files, $action, $request) {
            foreach ($files as $file) {
                $action === 'delete'
                    ? $file->delete()
                    : $file->update(['folder_id' => $request->filled('folder_id') ? $request->integer('folder_id') : null]);
            }
        });

        $key = $action === 'delete' ? 'media::admin.bulk_deleted' : 'media::admin.bulk_moved';

        return back()->with('success', trans_choice($key, $files->count(), ['count' => $files->count()]));
    }

    /**
     * The space the library uses: total, file count and size by filter chip (+ "other").
     *
     * @return array{total: int, count: int, groups: array<string, int>}
     */
    private function storage(): array
    {
        $byExtension = MediaFile::query()
            ->select('extension', DB::raw('SUM(size) as bytes'), DB::raw('COUNT(*) as files'))
            ->groupBy('extension')
            ->get();

        $groups = array_fill_keys([...array_keys(MediaFile::GROUPS), 'other'], 0);

        foreach ($byExtension as $row) {
            $kind = (new MediaFile(['extension' => $row->extension]))->kind();
            $group = collect(MediaFile::GROUPS)->search(fn (array $kinds) => in_array($kind, $kinds, true)) ?: 'other';
            $groups[$group] += (int) $row->getAttribute('bytes');
        }

        return [
            'total' => array_sum($groups),
            'count' => (int) $byExtension->sum('files'),
            'groups' => $groups,
        ];
    }
}
