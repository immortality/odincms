<?php

namespace Tests\Fixtures\Navigation;

use App\Cms\Modules\Module;
use App\Cms\Support\Linkable;
use Illuminate\Support\Collection;

/**
 * A Linkable model for navigation tests, reusing the modules table (tests can't create tables
 * inside the RefreshDatabase transaction).
 */
class LinkableModule extends Module implements Linkable
{
    protected $table = 'modules';

    public function linkUrl(): string
    {
        return url('/content/'.$this->slug);
    }

    public function linkLabel(): string
    {
        return $this->name;
    }

    public static function linkableSearch(string $query): Collection
    {
        return static::query()->where('name', 'like', '%'.$query.'%')->orderBy('name')->get();
    }

    public static function linkableOptions(): array
    {
        return static::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
