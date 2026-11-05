<?php

declare(strict_types=1);

namespace Modules\Auth\Config;

use Modules\Auth\Filters\AuthSession;
use Modules\Auth\Filters\ForcePasswordReset;

/**
 * Filter aliases of the Auth module (CodeIgniter registrar). `authsession` runs globally and
 * `session` + `forcereset` cover `backend/*` (app/Config/Filters.php).
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
                'authsession' => AuthSession::class,
                'forcereset'  => ForcePasswordReset::class,
            ],
        ];
    }
}
