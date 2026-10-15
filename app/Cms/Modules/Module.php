<?php

namespace App\Cms\Modules;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A module discovered in modules/ (one row per module.json).
 *
 * The lifecycle (install, enable, disable, uninstall, upgrade) is handled by
 * ModuleManager; don't change these flags directly.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $version Version in module.json
 * @property string|null $installed_version Version that was installed or last upgraded
 * @property bool $is_core
 * @property Carbon|null $installed_at
 * @property bool $enabled
 * @property bool $is_missing
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['slug', 'name', 'version', 'installed_version', 'is_core', 'installed_at', 'enabled', 'is_missing', 'sort_order'])]
#[UseFactory(ModuleFactory::class)]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'installed_at' => 'datetime',
            'enabled' => 'boolean',
            'is_missing' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Modules that are installed (enabled or not).
     *
     * @param  Builder<Module>  $query
     */
    #[Scope]
    protected function installed(Builder $query): void
    {
        $query->whereNotNull('installed_at');
    }

    /**
     * Installed, enabled modules whose folder still exists: the ones that are booted.
     *
     * @param  Builder<Module>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNotNull('installed_at')
            ->where('enabled', true)
            ->where('is_missing', false);
    }

    /**
     * Order for the Modules screen and the boot sequence.
     *
     * @param  Builder<Module>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function isInstalled(): bool
    {
        return $this->installed_at !== null;
    }

    public function isActive(): bool
    {
        return $this->isInstalled() && $this->enabled && ! $this->is_missing;
    }

    /**
     * The manifest version is newer than the installed one.
     */
    public function needsUpgrade(): bool
    {
        return $this->isInstalled()
            && $this->installed_version !== null
            && version_compare($this->version, $this->installed_version, '>');
    }
}
