<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use Closure;
use CodeIgniter\Cache\CacheInterface;

/**
 * The Core's caches, grouped in scopes (permissions, menus, settings, widgets, assets, modules).
 *
 * CodeIgniter's cache has no tags, so every scope has a version number stored in the cache and
 * every key includes it: invalidating a scope bumps its version and the old entries simply
 * expire. `CacheInvalidator` is the only service that invalidates.
 */
final class CoreCache
{
    public const TTL = 86400;

    public function __construct(private readonly ?CacheInterface $cache = null)
    {
    }

    /**
     * Returns the cached value of a key in a scope, computing and storing it on a miss.
     *
     * @template T
     *
     * @param Closure(): T $compute
     *
     * @return T
     */
    public function remember(string $scope, string $key, Closure $compute, int $ttl = self::TTL): mixed
    {
        $cacheKey = $this->key($scope, $key);
        $hit      = $this->cache()->get($cacheKey);

        if (is_array($hit) && array_key_exists('v', $hit)) {
            return $hit['v'];
        }

        $value = $compute();
        $this->cache()->save($cacheKey, ['v' => $value], $ttl);

        return $value;
    }

    /**
     * Drops every entry of a scope (by bumping its version).
     */
    public function invalidate(string $scope): void
    {
        $this->cache()->save($this->versionKey($scope), $this->version($scope) + 1, 0);
    }

    private function key(string $scope, string $key): string
    {
        return 'core_' . $scope . '_v' . $this->version($scope) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
    }

    private function version(string $scope): int
    {
        $version = $this->cache()->get($this->versionKey($scope));

        return is_int($version) ? $version : 0;
    }

    private function versionKey(string $scope): string
    {
        return 'core_' . $scope . '_version';
    }

    private function cache(): CacheInterface
    {
        return $this->cache ?? service('cache');
    }
}
