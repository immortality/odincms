<?php

namespace App\Cms\Support\Concerns;

use App\Cms\Support\CmsCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Flushes the CMS cache (CmsCache) whenever a record is saved or deleted, for content that cached
 * frontend parts are built from (widget areas, banners, the site contact…): the change shows at once.
 *
 * @mixin Model
 */
trait FlushesCmsCache
{
    public static function bootFlushesCmsCache(): void
    {
        $flush = fn () => app(CmsCache::class)->flush();

        static::saved($flush);
        static::deleted($flush);
    }
}
