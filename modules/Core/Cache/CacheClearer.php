<?php

declare(strict_types=1);

namespace Modules\Core\Cache;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Events\Events;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Theme\ThemeRegistry;

/**
 * Clears every cache of the application (`php spark app:cache-clear` and Settings › Actions
 * › Clear caches): the Core's scopes (through CacheInvalidator, which also fires
 * `modules.cacheInvalidate`) and the theme registry, then CodeIgniter's whole cache, then the `cache.cleared` event for
 * caches kept elsewhere (a module's files in `writable/`, for example).
 */
final class CacheClearer
{
    public const EVENT = 'cache.cleared';

    public function __construct(
        private readonly ?CacheInterface $cache = null,
        private readonly CacheInvalidator $invalidator = new CacheInvalidator(),
    ) {
    }

    public function clear(): bool
    {
        $this->invalidator->invalidate();
        ThemeRegistry::reset();
        service('theme')->reset();

        $cleaned = ($this->cache ?? cache())->clean();

        Events::trigger(self::EVENT);

        return $cleaned;
    }
}
