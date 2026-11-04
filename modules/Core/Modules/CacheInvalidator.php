<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Events\Events;
use Modules\Core\Cache\CoreCache;

/**
 * The single service that invalidates the caches derived from the manifests and the module
 * registry (step 8 of `modules:sync`, enable/disable, demo data).
 *
 * Each scope of `CoreCache` is dropped, then the `modules.cacheInvalidate` event fires with the
 * scopes so that caches kept elsewhere (later sessions) can clear themselves too.
 */
final class CacheInvalidator
{
    public const EVENT = 'modules.cacheInvalidate';

    public const SCOPES = ['permissions', 'menus', 'settings', 'widgets', 'assets', 'modules'];

    public function __construct(private readonly CoreCache $cache = new CoreCache())
    {
    }

    /**
     * @param list<string> $scopes
     */
    public function invalidate(array $scopes = self::SCOPES): void
    {
        foreach ($scopes as $scope) {
            $this->cache->invalidate($scope);
        }

        Events::trigger(self::EVENT, $scopes);
    }
}
