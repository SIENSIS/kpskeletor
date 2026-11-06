<?php

declare(strict_types=1);

namespace Modules\Auth\Config;

use Modules\Auth\Filters\AuthSession;
use Modules\Auth\Filters\ForcePasswordReset;
use Modules\Auth\Filters\ForceTwoFactor;
use Modules\Auth\Filters\RequireReauth;
use Modules\Auth\Validation\AuthRules;

/**
 * Filter aliases of the Auth module (CodeIgniter registrar). `authsession` runs globally and
 * `session`, `forcereset` and `force2fa` cover `backend/*`; `reauth` protects sensitive routes (app/Config/Filters.php).
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
                'force2fa'    => ForceTwoFactor::class,
                'reauth'      => RequireReauth::class,
            ],
        ];
    }

    /**
     * Rules of the Auth settings (`two_factor_policy`).
     *
     * @return array<string, mixed>
     */
    public static function Validation(): array
    {
        return ['ruleSets' => [AuthRules::class]];
    }
}
