<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Front-end assets (see docs/frontend.md). Extended by app/Config/Assets.php; overridable from
 * `.env` (`assets.devRefresh = false`).
 */
class Assets extends BaseConfig
{
    /**
     * Development only: republish the assets when a source in `modules/<Name>/Assets` or
     * `themes/<slug>/Assets` is newer than the asset manifest, so edits show up without running
     * `assets:publish`. Never used in production.
     */
    public bool $devRefresh = true;
}
