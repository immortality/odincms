<?php

namespace App\Cms\Modules\Exceptions;

use RuntimeException;

/**
 * A module.json that can't be read or doesn't follow the manifest rules (spec §5.2).
 */
class InvalidManifestException extends RuntimeException
{
    public static function at(string $path, string $problem): self
    {
        return new self("Invalid module manifest [{$path}]: {$problem}");
    }
}
