<?php

declare(strict_types=1);

namespace Modules\Devtools\Config;

use Modules\Devtools\Filters\LocalOnly;

/**
 * Filter alias of the Devtools module (CodeIgniter registrar): `devtools` answers 404 unless the
 * request comes from the loopback with a local `Host` (Modules\Devtools\Filters\LocalOnly).
 */
final class Registrar
{
    /**
     * @return array<string, mixed>
     */
    public static function Filters(): array
    {
        return ['aliases' => ['devtools' => LocalOnly::class]];
    }
}
