<?php

namespace App\Cms\Modules\Exceptions;

use RuntimeException;

/**
 * A module lifecycle action that isn't allowed (spec §5.3). The message is shown to the admin.
 */
class ModuleException extends RuntimeException
{
    public static function notFound(string $slug): self
    {
        return new self("Module \"{$slug}\" was not found. Check the modules folder and run cms:modules:sync.");
    }

    public static function folderMissing(string $slug): self
    {
        return new self("The folder of module \"{$slug}\" is missing or its module.json is invalid. Restore it and run cms:modules:sync.");
    }

    public static function alreadyInstalled(string $slug): self
    {
        return new self("Module \"{$slug}\" is already installed.");
    }

    public static function notInstalled(string $slug): self
    {
        return new self("Module \"{$slug}\" is not installed.");
    }

    /**
     * @param  list<string>  $missing
     */
    public static function missingDependencies(string $slug, array $missing): self
    {
        return new self("Module \"{$slug}\" requires ".self::list($missing).' to be installed and enabled first.');
    }

    /**
     * @param  list<string>  $dependents
     */
    public static function hasDependents(string $slug, string $action, array $dependents): self
    {
        return new self("Module \"{$slug}\" cannot be {$action}: ".self::list($dependents).' depend'.(count($dependents) === 1 ? 's' : '').' on it.');
    }

    public static function coreProtected(string $slug, string $action): self
    {
        return new self("Module \"{$slug}\" is a core module and cannot be {$action}.");
    }

    public static function noUpgrade(string $slug, string $installed, string $available): self
    {
        return new self("Module \"{$slug}\" has no upgrade available (installed {$installed}, available {$available}).");
    }

    /**
     * @param  list<string>  $slugs
     */
    private static function list(array $slugs): string
    {
        return implode(', ', array_map(fn (string $slug) => "\"{$slug}\"", $slugs));
    }
}
