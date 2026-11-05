<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Cookie\Cookie;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Config\Services;
use Modules\Auth\Models\UserSessionModel;

/**
 * Tracks every login session in `user_sessions` ("my sessions", remote logout, absolute
 * lifetime, privilege changes).
 *
 * The PHP session holds a random tracking token (it survives session id regeneration); only its
 * SHA-256 hash is stored. The remember-me selector of the session is stored too, so that ending
 * the session remotely also deletes its remember-me token and it is not silently re-created.
 */
final class SessionTracker
{
    public const SESSION_KEY = 'kp_auth_session';

    /** Seconds between two `last_seen_at` updates of the same session. */
    public const TOUCH_INTERVAL = 60;

    public const END_LOGOUT = 'logout';

    public const END_REVOKED = 'revoked';

    public const END_EXPIRED = 'expired';

    public const END_PASSWORD = 'password';

    public const END_IDLE = 'idle';

    /** Id of the current session's row, remembered for the request (Shield's logout clears the session first). */
    private static ?int $currentId = null;

    private readonly UserSessionModel $model;

    public function __construct(?UserSessionModel $model = null)
    {
        $this->model = $model ?? model(UserSessionModel::class);
    }

    /**
     * Starts tracking the current PHP session for a user who has just logged in.
     *
     * @return array<string, mixed> The new row
     */
    public function start(User $user, bool $remembered): array
    {
        $token   = bin2hex(random_bytes(32));
        $request = service('request');
        $now     = Time::now()->format('Y-m-d H:i:s');

        session()->set(self::SESSION_KEY, $token);

        $row = [
            'user_id'           => (int) $user->id,
            'token_hash'        => hash('sha256', $token),
            'ip_address'        => $request instanceof IncomingRequest ? $request->getIPAddress() : null,
            'user_agent'        => $request instanceof IncomingRequest ? mb_substr((string) $request->getUserAgent(), 0, 255) : null,
            'remembered'        => $remembered ? 1 : 0,
            'remember_selector' => $this->rememberSelector(),
            'regenerate'        => 0,
            'created_at'        => $now,
            'last_seen_at'      => $now,
        ];

        $row['id']       = (int) $this->model->insert($row, true);
        self::$currentId = $row['id'];

        return $row;
    }

    /**
     * The tracked row of the current PHP session, or null.
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        $token = session(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return null;
        }

        $row = $this->model->where('token_hash', hash('sha256', $token))->first();

        if (! is_array($row)) {
            return null;
        }

        self::$currentId = (int) $row['id'];

        return $row;
    }

    /**
     * Updates `last_seen_at` (at most once a minute) and records a remember-me selector that
     * appeared after the login.
     *
     * @param array<string, mixed> $row
     */
    public function touch(array $row): void
    {
        $update = [];
        $last   = is_string($row['last_seen_at'] ?? null) ? strtotime($row['last_seen_at']) : 0;

        if (time() - (int) $last >= self::TOUCH_INTERVAL) {
            $update['last_seen_at'] = Time::now()->format('Y-m-d H:i:s');
        }

        $selector = $this->rememberSelector();

        if ($selector !== null && $selector !== ($row['remember_selector'] ?? null)) {
            $update['remember_selector'] = $selector;
        }

        if ($update !== []) {
            $this->model->update($row['id'], $update);
        }
    }

    /**
     * Marks a row as ended.
     */
    public function end(int $id, string $reason): void
    {
        $this->model->where('id', $id)->where('ended_at', null)->set([
            'ended_at'   => Time::now()->format('Y-m-d H:i:s'),
            'end_reason' => $reason,
        ])->update();
    }

    /**
     * Ends the current PHP session's row (logout).
     */
    public function endCurrent(string $reason): void
    {
        $id = $this->current()['id'] ?? self::$currentId;

        if ($id !== null) {
            $this->end((int) $id, $reason);
        }

        self::$currentId = null;
        session()->remove(self::SESSION_KEY);
    }

    /**
     * Logs out one session of a user from another device: the row is ended and its
     * remember-me token deleted. Only the owner's sessions can be revoked.
     */
    public function revoke(int $userId, int $sessionId): bool
    {
        $row = $this->model->where('id', $sessionId)->where('user_id', $userId)->where('ended_at', null)->first();

        if (! is_array($row)) {
            return false;
        }

        $this->end((int) $row['id'], self::END_REVOKED);
        $this->forgetSelector($row['remember_selector'] ?? null);

        return true;
    }

    /**
     * Ends every session of a user except the current one (password change, "log out other
     * sessions") and deletes all the user's remember-me tokens except the current session's.
     *
     * @return int Number of sessions ended
     */
    public function endOthers(int $userId, string $reason): int
    {
        $current = $this->current();
        $keepId  = $current !== null && (int) $current['user_id'] === $userId ? (int) $current['id'] : 0;
        $keepSel = $keepId > 0 ? ($current['remember_selector'] ?? null) : null;
        $ended   = 0;

        foreach ($this->model->activeFor($userId) as $row) {
            if ((int) $row['id'] === $keepId) {
                continue;
            }

            $this->end((int) $row['id'], $reason);
            $ended++;
        }

        $tokens = db_connect()->table(config('Auth')->tables['remember_tokens'])->where('user_id', $userId);

        if (is_string($keepSel) && $keepSel !== '') {
            $tokens->where('selector !=', $keepSel);
        }

        $tokens->delete();

        return $ended;
    }

    /**
     * After a change of groups, permissions or impersonation: the current session id is
     * regenerated now and the user's other sessions on their next request.
     */
    public function privilegesChanged(int $userId): void
    {
        $this->model->where('user_id', $userId)->where('ended_at', null)->set(['regenerate' => 1])->update();

        $current = $this->current();

        if ($current !== null && (int) $current['user_id'] === $userId) {
            $this->regenerate($current);
        }
    }

    /**
     * Regenerates the PHP session id (fixation protection) and clears the row's flag.
     *
     * @param array<string, mixed> $row
     */
    public function regenerate(array $row): void
    {
        session()->regenerate(true);
        Services::security()->generateHash();
        $this->model->update($row['id'], ['regenerate' => 0]);
    }

    /**
     * Active sessions of a user. Rows past the absolute lifetime (the browser was closed or the
     * session was collected) are ended as expired first.
     *
     * @return list<array<string, mixed>>
     */
    public function activeFor(int $userId): array
    {
        $limit = Time::now()->subSeconds((new AuthPolicy())->absoluteLifetime())->format('Y-m-d H:i:s');

        $this->model->where('user_id', $userId)->where('ended_at', null)->where('created_at <', $limit)->set([
            'ended_at'   => Time::now()->format('Y-m-d H:i:s'),
            'end_reason' => self::END_EXPIRED,
        ])->update();

        return $this->model->activeFor($userId);
    }

    /**
     * Forgets the remembered row id (tests and long-running workers).
     */
    public static function reset(): void
    {
        self::$currentId = null;
    }

    /**
     * Deletes the remember-me token with this selector.
     */
    public function forgetSelector(mixed $selector): void
    {
        if (is_string($selector) && $selector !== '') {
            db_connect()->table(config('Auth')->tables['remember_tokens'])->where('selector', $selector)->delete();
        }
    }

    /**
     * The selector of the remember-me cookie set in this response or sent with this request.
     */
    public function rememberSelector(): ?string
    {
        $name     = (string) config('Auth')->sessionConfig['rememberCookieName'];
        $response = service('response');
        $value    = null;

        if ($response->hasCookie($name, null, (string) config('Cookie')->prefix)) {
            $cookie = $response->getCookie($name, (string) config('Cookie')->prefix);
            $value  = $cookie instanceof Cookie ? $cookie->getValue() : null;
        }

        if ($value === null || $value === '') {
            $request = service('request');
            $value   = $request instanceof IncomingRequest ? $request->getCookie((string) config('Cookie')->prefix . $name) : null;
        }

        if (! is_string($value) || ! str_contains($value, ':')) {
            return null;
        }

        $selector = explode(':', $value, 2)[0];

        return preg_match('/^[a-f0-9]{1,64}$/', $selector) === 1 ? $selector : null;
    }
}
