<?php

declare(strict_types=1);

namespace Modules\Api\Services;

use Config\Cors;
use Config\Kpapy as KpapyConfig;
use Modules\Auth\Services\PasswordChanger;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Throwable;

/**
 * The Api module's part of `security:check` (event `securityChecks`):
 *
 * - `configuration.cors`: the API's CORS is not open to `*` (kpapy's `corsOrigins`, from
 *   `Api.corsOrigins`, and CodeIgniter's `Config\Cors`);
 * - `accounts.personalTokens` (information): personal API tokens without an expiry date.
 */
final class ApiSecurityCheck implements SecurityCheck
{
    /**
     * @param list<string>|null $corsOrigins  (tests) default: Config\Kpapy and Config\Cors
     * @param int|null          $neverExpire  (tests) personal tokens without expiry
     */
    public function __construct(private readonly ?array $corsOrigins = null, private readonly ?int $neverExpire = null)
    {
    }

    public function groups(): array
    {
        return ['configuration', 'accounts'];
    }

    public function run(SecurityReport $report): void
    {
        $origins = $this->corsOrigins ?? [...config(KpapyConfig::class)->corsOrigins, ...config(Cors::class)->default['allowedOrigins'], ...config(Cors::class)->default['allowedOriginsPatterns']];
        $open    = array_values(array_filter($origins, static fn (string $origin): bool => in_array(trim($origin), ['*', '.*', 'null'], true)));
        $title   = lang('Api.securityCheck.cors');

        if ($open !== []) {
            $report->error('configuration', 'configuration.cors', $title, lang('Api.securityCheck.corsOpen'), lang('Api.securityCheck.fixCors'));
        } elseif ($origins === []) {
            $report->ok('configuration', 'configuration.cors', $title, lang('Api.securityCheck.corsClosed'));
        } else {
            $report->ok('configuration', 'configuration.cors', $title, lang('Api.securityCheck.corsOrigins', [implode(', ', $origins)]));
        }

        $count = $this->neverExpire ?? self::countNeverExpiring();

        if ($count === null) {
            return;
        }

        $tokens = lang('Api.securityCheck.tokens');

        if ($count > 0) {
            $report->info('accounts', 'accounts.personalTokens', $tokens, lang('Api.securityCheck.tokensNoExpiry', [$count]), lang('Api.securityCheck.fixTokens'));
        } else {
            $report->ok('accounts', 'accounts.personalTokens', $tokens, lang('Api.securityCheck.tokensOk'));
        }
    }

    private static function countNeverExpiring(): ?int
    {
        try {
            return db_connect()->table('auth_identities')
                ->where('type', 'access_token')
                ->where('secret2', PasswordChanger::PERSONAL_TOKEN)
                ->where('expires', null)
                ->countAllResults();
        } catch (Throwable) {
            return null;
        }
    }
}
