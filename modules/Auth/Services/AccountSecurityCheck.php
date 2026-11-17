<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Throwable;

/**
 * The Auth module's part of `security:check` (event `securityChecks`), group `accounts`:
 * superadmins without 2FA (warning), groups where 2FA is optional (information) and the minimum
 * password length (warning below 12).
 */
final class AccountSecurityCheck implements SecurityCheck
{
    public const RECOMMENDED_PASSWORD_LENGTH = 12;

    /**
     * @param list<string>|null          $superadminsWithout2fa (tests) usernames or emails
     * @param array<string, string>|null $policy                (tests) group => required|optional
     */
    public function __construct(
        private readonly ?array $superadminsWithout2fa = null,
        private readonly ?array $policy = null,
        private readonly ?int $minimumPasswordLength = null,
    ) {
    }

    public function groups(): array
    {
        return ['accounts'];
    }

    public function run(SecurityReport $report): void
    {
        $without = $this->superadminsWithout2fa ?? self::superadminsWithout2fa();
        $title   = lang('Auth.securityCheck.superadmins');

        if ($without === null) {
            $report->info('accounts', 'accounts.superadmin2fa', $title, lang('Auth.securityCheck.unknown'));
        } elseif ($without !== []) {
            $report->warning('accounts', 'accounts.superadmin2fa', $title, lang('Auth.securityCheck.superadminsWithout', [count($without), implode(', ', array_slice($without, 0, 5))]), lang('Auth.securityCheck.fixSuperadmins'));
        } else {
            $report->ok('accounts', 'accounts.superadmin2fa', $title, lang('Auth.securityCheck.superadminsOk'));
        }

        $policy   = $this->policy ?? GroupRegistry::twoFactorPolicy();
        $optional = array_keys(array_filter($policy, static fn (string $value): bool => $value !== GroupRegistry::REQUIRED));
        $title    = lang('Auth.securityCheck.groups');

        if ($optional === []) {
            $report->ok('accounts', 'accounts.group2fa', $title, lang('Auth.securityCheck.groupsOk'));
        } else {
            $report->info('accounts', 'accounts.group2fa', $title, lang('Auth.securityCheck.groupsOptional', [implode(', ', $optional)]), lang('Auth.securityCheck.fixGroups'));
        }

        $length = $this->minimumPasswordLength ?? (new AuthPolicy())->minimumPasswordLength();
        $title  = lang('Auth.securityCheck.password');

        if ($length < self::RECOMMENDED_PASSWORD_LENGTH) {
            $report->warning('accounts', 'accounts.passwordLength', $title, lang('Auth.securityCheck.passwordShort', [$length, self::RECOMMENDED_PASSWORD_LENGTH]), lang('Auth.securityCheck.fixPassword', [self::RECOMMENDED_PASSWORD_LENGTH]));
        } else {
            $report->ok('accounts', 'accounts.passwordLength', $title, lang('Auth.securityCheck.passwordOk', [$length]));
        }
    }

    /**
     * Active superadmins without a second factor (username or email), null when unknown.
     *
     * @return list<string>|null
     */
    private static function superadminsWithout2fa(): ?array
    {
        try {
            $rows = db_connect()->table('auth_groups_users g')
                ->select('u.id, u.username, i.secret AS email')
                ->join('users u', 'u.id = g.user_id')
                ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
                ->join('user_two_factor t', 't.user_id = u.id', 'left')
                ->where('g.group', 'superadmin')
                ->where('u.deleted_at', null)
                ->where('t.id', null)
                ->get()->getResultArray();
        } catch (Throwable) {
            return null;
        }

        return array_values(array_unique(array_map(static fn (array $row): string => (string) ($row['username'] ?? '') !== '' ? (string) $row['username'] : (string) ($row['email'] ?? '#' . $row['id']), $rows)));
    }
}
