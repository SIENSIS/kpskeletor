<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use Modules\Core\Filters\Can;
use Modules\Core\Filters\ContentSecurity;
use Modules\Core\Filters\CsrfTokenHeader;
use Modules\Core\Filters\ModuleEnabled;
use Modules\Core\Filters\PoorMansCron;
use Modules\Core\Filters\RateLimit;
use Modules\Core\Settings\SecurityRules;

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
                'csrftoken'     => CsrfTokenHeader::class,
                'moduleenabled' => ModuleEnabled::class,
                'poormanscron'  => PoorMansCron::class,
                'ratelimit'     => RateLimit::class,
            ],
        ];
    }

    /**
     * Rules that refuse values weakening the security baseline (Settings › Security).
     *
     * @return array<string, mixed>
     */
    public static function Validation(): array
    {
        return ['ruleSets' => [SecurityRules::class]];
    }
}
