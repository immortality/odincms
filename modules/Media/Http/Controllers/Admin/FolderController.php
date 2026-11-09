<?php

namespace Modules\Media\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Modules\Media\Models\MediaFolder;

class FolderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('media.edit');

        $data = $request->validateWithBag('folder', [
            'parent_id' => ['nullable', 'integer', Rule::exists('media_folders', 'id')],
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F\/\\\\]/', $this->uniqueName($request->input('parent_id'))],
        ]);

        $folder = MediaFolder::create(['parent_id' => $data['parent_id'] ?? null, 'name' => trim($data['name'])]);

        return redirect()->route('admin.media.index', ['folder' => $folder->id])
            ->with('success', __('media::admin.folder_created'));
    }

    public function update(Request $request, MediaFolder $folder): RedirectResponse
    {
        Gate::authorize('media.edit');

        $data = $request->validateWithBag('folder', [
            'name' => ['required', 'string', 'max:120', 'not_regex:/[\x00-\x1F\x7F\/\\\\]/', $this->uniqueName($folder->parent_id, $folder->id)],
        ]);

        $folder->update(['name' => trim($data['name'])]);

        return redirect()->route('admin.media.index', ['folder' => $folder->id])
            ->with('success', __('media::admin.folder_renamed'));
    }

    /**
     * Only empty folders: files are never deleted along with a folder by accident.
     */
    public function destroy(MediaFolder $folder): RedirectResponse
    {
        Gate::authorize('media.delete');

        if (! $folder->isEmpty()) {
            return back()->with('error', __('media::admin.folder_not_empty'));
        }

        $parent = $folder->parent_id;
        $folder->delete();

        return redirect()->route('admin.media.index', array_filter(['folder' => $parent]))
            ->with('success', __('media::admin.folder_deleted'));
    }

    /**
     * No two folders with the same name in one parent.
     */
    private function uniqueName(mixed $parentId, ?int $except = null): Unique
    {
        return Rule::unique('media_folders', 'name')
            ->where(fn ($query) => is_numeric($parentId) ? $query->where('parent_id', (int) $parentId) : $query->whereNull('parent_id'))
            ->ignore($except);
    }
}
