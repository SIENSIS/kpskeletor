<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use Modules\Core\Filters\Can;
use Modules\Core\Filters\ContentSecurity;
use Modules\Core\Filters\ModuleEnabled;

/**
 * Merges the Core's settings into the application's config classes (CodeIgniter registrar).
 */
final class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'can'           => Can::class,
                'cspmodules'    => ContentSecurity::class,
                'moduleenabled' => ModuleEnabled::class,
            ],
        ];
    }
}
