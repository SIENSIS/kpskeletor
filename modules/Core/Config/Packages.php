<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Module package options (decision D-00-05): demo data in production, and later the trusted
 * publisher keys of signed packages (session 29).
 *
 * Overridden by `app/Config/Packages.php` and `.env` (`packages.allowDemoInProduction = true`);
 * read as settings (`setting('Packages.allowDemoInProduction')`). CodeIgniter's own
 * `Config\Modules` is never extended.
 */
class Packages extends BaseConfig
{
    /**
     * Allows loading or removing demo data when CI_ENVIRONMENT is production. Off by default.
     */
    public bool $allowDemoInProduction = false;
}
