<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseService;
use Modules\Core\Theme\ThemeManager;

/**
 * Core services (discovered by CodeIgniter through the module namespace).
 */
class Services extends BaseService
{
    /**
     * The themes of the current request: active theme per area, view resolution, options,
     * regions and assets.
     */
    public static function theme(bool $getShared = true): ThemeManager
    {
        if ($getShared) {
            return static::getSharedInstance('theme');
        }

        return new ThemeManager();
    }
}
