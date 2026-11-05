<?php

declare(strict_types=1);

namespace Modules\Api\Config;

use Modules\Api\Filters\ApiLocale;

/**
 * Filter aliases of the Api module (CodeIgniter registrar): `apilocale` runs on `api/*`
 * (app/Config/Filters.php). kpapy registers its own `kpapy` alias.
 */
final class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Filters(): array
    {
        return ['aliases' => ['apilocale' => ApiLocale::class]];
    }
}
