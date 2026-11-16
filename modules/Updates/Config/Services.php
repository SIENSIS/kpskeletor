<?php

declare(strict_types=1);

namespace Modules\Updates\Config;

use CodeIgniter\Config\BaseService;
use Modules\Updates\Services\ReleaseChecker;

/**
 * Updates services (discovered through the module namespace).
 */
class Services extends BaseService
{
    /**
     * The update check used by the page, the task and `updates:check` (tests inject one that
     * reads a fake repository).
     */
    public static function updatechecker(bool $getShared = true): ReleaseChecker
    {
        if ($getShared) {
            return static::getSharedInstance('updatechecker');
        }

        return new ReleaseChecker();
    }
}
