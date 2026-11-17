<?php

declare(strict_types=1);

namespace Config;

use Modules\Core\Config\ProductionCheck as CoreProductionCheck;

/**
 * Options of `security:check` for this installation (see Modules\Core\Config\ProductionCheck
 * for every key). `.env` overrides them too, e.g. `productioncheck.webUser = www-data`.
 */
class ProductionCheck extends CoreProductionCheck
{
}
