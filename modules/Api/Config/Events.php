<?php

declare(strict_types=1);

/**
 * Events of the Api module (discovered by CodeIgniter): kpapy's app-login events go to the
 * activity log (docs/activity.md).
 *
 * - `kpapy.auth.challenge` (password right, second factor asked) and `kpapy.auth.login` (token
 *   issued) as activity; `kpapy.auth.logout` (token revoked by its app) as activity;
 * - `kpapy.auth.failed` (wrong password or code) and `kpapy.auth.denied` ("it was not me" link)
 *   as security entries.
 */

use CodeIgniter\Events\Events;
use Modules\Core\Activity\ActivityLogger;

$apiUserId = static function (array $data): ?int {
    $user = $data['user'] ?? null;

    if (is_object($user) && isset($user->id)) {
        return (int) $user->id;
    }

    return isset($data['user_id']) && is_numeric($data['user_id']) ? (int) $data['user_id'] : null;
};

Events::on('kpapy.auth.challenge', static function (array $data) use ($apiUserId): void {
    activity_log('Api', 'api_login_challenge', ['user_id' => $apiUserId($data), 'resource' => 'api', 'summary' => (string) ($data['method'] ?? '')]);
});

Events::on('kpapy.auth.login', static function (array $data) use ($apiUserId): void {
    security_log('Api', 'token_created', [
        'user_id'   => $apiUserId($data),
        'resource'  => 'api',
        'record_id' => isset($data['token_id']) ? (string) $data['token_id'] : null,
        'summary'   => (string) ($data['method'] ?? ''),
        'changes'   => ['app_login' => true],
    ]);
});

Events::on('kpapy.auth.logout', static function (array $data) use ($apiUserId): void {
    security_log('Api', 'token_revoked', ['user_id' => $apiUserId($data), 'resource' => 'api', 'record_id' => isset($data['token_id']) ? (string) $data['token_id'] : null]);
});

Events::on('kpapy.auth.failed', static function (array $data) use ($apiUserId): void {
    security_log('Api', 'api_login_failed', [
        'user_id'  => null,
        'resource' => 'api',
        'summary'  => ActivityLogger::typedEmail($data['email'] ?? (is_object($data['user'] ?? null) ? ($data['user']->email ?? '') : '')),
        'changes'  => ['method' => (string) ($data['method'] ?? ''), 'user' => $apiUserId($data)],
    ]);
});

Events::on('kpapy.auth.denied', static function (array $data) use ($apiUserId): void {
    security_log('Api', 'api_login_denied', ['user_id' => $apiUserId($data), 'resource' => 'api', 'record_id' => isset($data['challenge']) ? (string) $data['challenge'] : null]);
});
