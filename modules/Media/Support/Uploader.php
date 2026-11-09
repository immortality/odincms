<?php

namespace Modules\Media\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\Media\Models\MediaFile;
use Modules\Media\Models\MediaFolder;

/**
 * Stores an uploaded file on the public disk under media/{year}/{month}/ with a readable, unique name
 * ("team-photo-k3x9qa.jpg") and records it in the library.
 */
class Uploader
{
    public const DISK = 'public';

    /**
     * Validation rules for one uploaded file.
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        $extensions = implode(',', MediaFile::extensions());

        // extensions: the name must end in an accepted one; mimes: the content must match one too.
        return ['required', 'file', 'max:'.MediaFile::maxUploadKb(), 'extensions:'.$extensions, 'mimes:'.$extensions];
    }

    public function store(UploadedFile $upload, ?MediaFolder $folder, ?int $userId): MediaFile
    {
        $extension = strtolower($upload->getClientOriginalExtension());
        $base = Str::limit(Str::slug(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME)), 80, '') ?: 'file';

        $path = $upload->storeAs(
            'media/'.now()->format('Y/m'),
            $base.'-'.Str::lower(Str::random(6)).'.'.$extension,
            self::DISK,
        );

        [$width, $height] = $this->dimensions($upload, $extension);

        return MediaFile::create([
            'folder_id' => $folder?->id,
            'name' => $this->displayName($upload->getClientOriginalName(), $extension),
            'disk' => self::DISK,
            'path' => $path,
            'extension' => $extension,
            'mime_type' => (string) ($upload->getMimeType() ?: $upload->getClientMimeType()),
            'size' => (int) $upload->getSize(),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * The original name without folders and control characters, kept short; the extension stays.
     */
    private function displayName(string $original, string $extension): string
    {
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $original));
        $stem = Str::limit(pathinfo($name, PATHINFO_FILENAME), 180, '');

        return ($stem !== '' ? $stem : 'file').'.'.$extension;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function dimensions(UploadedFile $upload, string $extension): array
    {
        if (! in_array($extension, MediaFile::KINDS['image'], true)) {
            return [null, null];
        }

        $size = @getimagesize($upload->getRealPath());

        return $size ? [(int) $size[0], (int) $size[1]] : [null, null];
    }
}
