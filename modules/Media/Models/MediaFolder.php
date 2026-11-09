<?php

namespace Modules\Media\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder of the media library. Folders nest; only empty folders can be deleted.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 */
class MediaFolder extends Model
{
    protected $fillable = ['parent_id', 'name'];

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<MediaFolder, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'folder_id');
    }

    public function isEmpty(): bool
    {
        return ! $this->children()->exists() && ! $this->files()->exists();
    }

    /**
     * The folder and its parents, the top one first (for the path above the files).
     *
     * @return list<MediaFolder>
     */
    public function trail(): array
    {
        $trail = [];
        $folder = $this;

        while ($folder !== null && count($trail) < 50) {
            array_unshift($trail, $folder);
            $folder = $folder->parent;
        }

        return $trail;
    }

    /**
     * Ids of this folder and every folder below it.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $level = [$this->id];

        while ($level !== []) {
            $level = self::whereIn('parent_id', $level)->pluck('id')->all();
            $ids = [...$ids, ...$level];
        }

        return $ids;
    }

    /**
     * Every folder in tree order, each with "depth" and "trail" ("Photos / Team") set, for the sidebar
     * tree and the "Move to" select.
     *
     * @return Collection<int, MediaFolder>
     */
    public static function tree(): Collection
    {
        $byParent = self::query()->orderBy('name')->get()->groupBy(fn (self $folder) => (int) $folder->parent_id);
        $result = new Collection;

        $walk = function (int $parentId, int $depth, string $trail) use (&$walk, $byParent, $result): void {
            foreach ($byParent->get($parentId, []) as $folder) {
                $folder->setAttribute('depth', $depth);
                $folder->setAttribute('trail', ltrim($trail.' / '.$folder->name, ' /'));
                $result->push($folder);
                $walk($folder->id, $depth + 1, $folder->getAttribute('trail'));
            }
        };
        $walk(0, 0, '');

        return $result;
    }
}
