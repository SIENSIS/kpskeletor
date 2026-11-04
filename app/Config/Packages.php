<?php

declare(strict_types=1);

namespace Config;

use Modules\Core\Config\Packages as CorePackages;

/**
 * Module package options of this installation (see Modules\Core\Config\Packages for every key).
 * `.env` overrides them too, e.g. `packages.allowDemoInProduction = true`.
 */
class Packages extends CorePackages
{
}
