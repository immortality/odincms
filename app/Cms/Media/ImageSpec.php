<?php

namespace App\Cms\Media;

/**
 * The sizes of one cropped image of a module, e.g. "services.photo": the main size ("large", the
 * crop frame's proportions) and smaller versions ("thumb", …).
 */
final readonly class ImageSpec
{
    /**
     * @param  string  $key  "{module}.{image}", e.g. "services.photo"
     * @param  string  $label  translation key or text
     * @param  array<string, array{0: int, 1: int}>  $sizes  the smaller versions: name => [width, height]
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $width,
        public int $height,
        public array $sizes = [],
    ) {}

    /**
     * Every version to generate: "large" (the main size) first.
     *
     * @return array<string, array{0: int, 1: int}>
     */
    public function versions(): array
    {
        return ['large' => [$this->width, $this->height]] + $this->sizes;
    }

    public function aspectRatio(): float
    {
        return $this->width / $this->height;
    }

    /**
     * "1200×800"
     */
    public function dimensions(): string
    {
        return $this->width.'×'.$this->height;
    }
}
