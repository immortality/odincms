<?php

namespace App\Cms\Support;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * Cache for CMS data that must be cleared together: the admin menu, widget output,
 * navigation, ... (spec §12).
 *
 * Keys carry a generation number; flush() increments it, which invalidates every
 * key at once. That works with any cache store, including "file", which has no tags.
 */
class CmsCache
{
    private const GENERATION_KEY = 'cms.cache.generation';

    public function __construct(private Repository $cache) {}

    /**
     * The full cache key for $key in the current generation.
     */
    public function key(string $key): string
    {
        return 'cms.'.$this->generation().'.'.$key;
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $key, int $seconds, Closure $callback): mixed
    {
        return $this->cache->remember($this->key($key), $seconds, $callback);
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return $this->cache->rememberForever($this->key($key), $callback);
    }

    public function forget(string $key): void
    {
        $this->cache->forget($this->key($key));
    }

    /**
     * Invalidate every CMS cache key.
     */
    public function flush(): void
    {
        $this->cache->forever(self::GENERATION_KEY, $this->generation() + 1);
    }

    private function generation(): int
    {
        $generation = $this->cache->get(self::GENERATION_KEY, 1);

        return is_int($generation) ? $generation : 1;
    }
}
