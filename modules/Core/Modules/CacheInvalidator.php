<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Events\Events;

/**
 * Invalidates the caches derived from the manifests (step 8 of `modules:sync`).
 *
 * The caches themselves (permissions, menus, settings, widgets, asset manifest) are added by
 * later sessions: each one deletes its entries when the `modules.cacheInvalidate` event fires.
 */
final class CacheInvalidator
{
    public const EVENT = 'modules.cacheInvalidate';

    public const SCOPES = ['permissions', 'menus', 'settings', 'widgets', 'assets'];

    /**
     * @param list<string> $scopes
     */
    public function invalidate(array $scopes = self::SCOPES): void
    {
        Events::trigger(self::EVENT, $scopes);
    }
}
