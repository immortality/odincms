<?php

namespace Modules\Media\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * A file in the media library. The file lives on `disk` at `path`; `name` is what admins see and may
 * rename (the stored path does not change, so links to it keep working).
 *
 * @property int $id
 * @property int|null $folder_id
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property int|null $uploaded_by
 */
class MediaFile extends Model
{
    /**
     * Accepted extensions by kind. The kinds match the file cards of odinadmin/js/file-manager.js.
     * No SVG or HTML: they can carry scripts.
     */
    public const KINDS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
        'pdf' => ['pdf'],
        'doc' => ['doc', 'docx', 'odt', 'rtf', 'txt'],
        'sheet' => ['xls', 'xlsx', 'ods', 'csv'],
        'slides' => ['ppt', 'pptx', 'odp'],
        'archive' => ['zip', 'rar', '7z'],
        'video' => ['mp4', 'webm', 'mov'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a'],
    ];

    /** The filter chips above the files: chip => kinds. */
    public const GROUPS = [
        'image' => ['image'],
        'doc' => ['pdf', 'doc', 'sheet', 'slides'],
        'video' => ['video', 'audio'],
        'archive' => ['archive'],
    ];

    /** kind => [palette class, icon] */
    private const LOOK = [
        'image' => ['violet', 'fas fa-file-image'],
        'pdf' => ['danger', 'fas fa-file-pdf'],
        'doc' => ['info', 'fas fa-file-word'],
        'sheet' => ['success', 'fas fa-file-excel'],
        'slides' => ['warning', 'fas fa-file-powerpoint'],
        'archive' => ['dark', 'fas fa-file-zipper'],
        'video' => ['danger', 'fas fa-file-video'],
        'audio' => ['info', 'fas fa-file-audio'],
        'other' => ['light', 'fas fa-file'],
    ];

    /** The largest file the library accepts, in kilobytes (PHP's upload limits may be lower). */
    public const MAX_KB = 51200;

    protected $fillable = [
        'folder_id', 'name', 'disk', 'path', 'extension', 'mime_type', 'size', 'width', 'height', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (MediaFile $file) {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    /**
     * @return BelongsTo<MediaFolder, $this>
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Files of a filter chip ("image", "doc", "video", "archive").
     *
     * @param  Builder<MediaFile>  $query
     */
    public function scopeOfGroup(Builder $query, string $group): void
    {
        $extensions = collect(self::GROUPS[$group] ?? [])->flatMap(fn (string $kind) => self::KINDS[$kind])->all();

        $query->whereIn('extension', $extensions);
    }

    /**
     * @return list<string>
     */
    public static function extensions(): array
    {
        return array_merge(...array_values(self::KINDS));
    }

    /**
     * The upload limit in kilobytes: MAX_KB, or less when PHP's upload_max_filesize / post_max_size are lower.
     */
    public static function maxUploadKb(): int
    {
        $limits = [self::MAX_KB];

        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $bytes = self::iniBytes((string) ini_get($setting));
            if ($bytes > 0) {
                $limits[] = intdiv($bytes, 1024);
            }
        }

        return min($limits);
    }

    public function kind(): string
    {
        foreach (self::KINDS as $kind => $extensions) {
            if (in_array($this->extension, $extensions, true)) {
                return $kind;
            }
        }

        return 'other';
    }

    public function tone(): string
    {
        return self::LOOK[$this->kind()][0];
    }

    public function icon(): string
    {
        return self::LOOK[$this->kind()][1];
    }

    public function isImage(): bool
    {
        return $this->kind() === 'image';
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * "2.4 MB"
     */
    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: $this->size >= 1024 ** 2 ? 1 : 0);
    }

    /**
     * "1200 × 800" for images, null otherwise.
     */
    public function dimensions(): ?string
    {
        return $this->width && $this->height ? $this->width.' × '.$this->height : null;
    }

    private static function iniBytes(string $size): int
    {
        $value = (int) $size;

        return match (strtolower(substr(trim($size), -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
