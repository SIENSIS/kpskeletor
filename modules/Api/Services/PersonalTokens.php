<?php

declare(strict_types=1);

namespace Modules\Api\Services;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Kpapy as KpapyConfig;
use InvalidArgumentException;
use Kpapy\Auth\TokenService;
use Modules\Api\Models\TokenIpModel;
use Modules\Auth\Services\PasswordChanger;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Permissions\PermissionCatalogue;

/**
 * Personal API tokens of the web profile (kpapy's `TokenService`): created only here, never
 * through the API, marked personal (`secret2 = 'personal'`, so a password change keeps them,
 * D-05-…), with a name, scopes and an expiry.
 *
 * Scopes are `*` (everything the user may do) or permissions the user holds now; a token can
 * only ever do the intersection of its scopes and the user's permissions at the time of each
 * request (kpapy), so scopes never add anything.
 */
final class PersonalTokens
{
    /** Lifetimes offered, in days (no token without expiry). */
    public const DAYS = [7, 30, 90, 365];

    public const DEFAULT_DAYS = 90;

    /**
     * Scopes the user may give a new token: `*` and the catalogue permissions they hold.
     *
     * @return list<string>
     */
    public function availableScopes(User $user): array
    {
        $authorization = Authorization::for($user);
        $scopes        = [];

        foreach ((new PermissionCatalogue())->enabledNames() as $name) {
            if ($authorization->can($name)) {
                $scopes[] = $name;
            }
        }

        return $scopes;
    }

    /**
     * Creates a token; the raw token is in `token` and is never visible again.
     *
     * @param list<string> $scopes `['*']` or permissions from `availableScopes()`
     *
     * @return array{token: string, id: int, name: string, scopes: list<string>, expires_at: string|null}
     */
    public function create(User $user, string $name, array $scopes, int $days): array
    {
        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 64) {
            throw new InvalidArgumentException(lang('Api.personal.invalidName'));
        }

        if (! in_array($days, self::DAYS, true)) {
            throw new InvalidArgumentException(lang('Api.personal.invalidDays'));
        }

        $scopes = array_values(array_unique(array_filter($scopes, 'is_string')));

        if ($scopes === [] || (in_array('*', $scopes, true) && count($scopes) > 1) || ($scopes !== ['*'] && array_diff($scopes, $this->availableScopes($user)) !== [])) {
            throw new InvalidArgumentException(lang('Api.personal.invalidScopes'));
        }

        $token = (new TokenService(config(KpapyConfig::class)))->create($user, $name, $scopes, $days * DAY);

        model(UserIdentityModel::class)->builder()->where('id', $token['id'])->where('user_id', $user->id)
            ->update(['secret2' => PasswordChanger::PERSONAL_TOKEN]);

        log_message('notice', 'User {id} created the personal API token {token}.', ['id' => $user->id, 'token' => $token['id']]);

        return $token;
    }

    /**
     * The user's tokens (personal and from app logins), without secrets.
     *
     * @return list<array{id: int, name: string, personal: bool, scopes: list<string>, created_at: string|null, last_used_at: string|null, expires_at: string|null, ip: string}>
     */
    public function list(User $user): array
    {
        $tokens = [];

        foreach ($user->accessTokens() as $token) {
            $tokens[] = [
                'id'           => (int) $token->id,
                'name'         => (string) $token->name,
                'personal'     => ($token->secret2 ?? null) === PasswordChanger::PERSONAL_TOKEN,
                'scopes'       => array_values((array) $token->scopes),
                'created_at'   => $token->created_at?->format('Y-m-d H:i:s'),
                'last_used_at' => $token->last_used_at?->format('Y-m-d H:i:s'),
                'expires_at'   => $token->expires?->format('Y-m-d H:i:s'),
                'ip'           => '',
            ];
        }

        $ips = (new TokenIpModel())->forTokens(array_column($tokens, 'id'));

        foreach ($tokens as $i => $token) {
            $tokens[$i]['ip'] = $ips[$token['id']] ?? '';
        }

        return $tokens;
    }

    /**
     * Revokes one of the user's own tokens; false when it is not theirs.
     */
    public function revoke(User $user, int $id): bool
    {
        if (! (new TokenService(config(KpapyConfig::class)))->revoke($user, $id)) {
            return false;
        }

        log_message('notice', 'User {id} revoked the API token {token}.', ['id' => $user->id, 'token' => $id]);

        return true;
    }

    /**
     * Whether an `auth_identities` row is a personal token.
     *
     * @param array<string, mixed> $row
     */
    public static function isPersonal(array $row): bool
    {
        return ($row['secret2'] ?? null) === PasswordChanger::PERSONAL_TOKEN;
    }

    /**
     * The scopes stored by Shield in `auth_identities.extra` (serialized list).
     *
     * @return list<string>
     */
    public static function scopesOf(mixed $extra): array
    {
        if (! is_string($extra) || $extra === '') {
            return [];
        }

        $scopes = @unserialize($extra, ['allowed_classes' => false]);

        return is_array($scopes) ? array_values(array_filter($scopes, 'is_string')) : [];
    }
}
