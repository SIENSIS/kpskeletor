<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\DeviceName;
use Modules\Auth\Services\PasswordChanger;
use Modules\Auth\Services\SessionTracker;
use Modules\Core\Controllers\BackendController;

/**
 * "My sessions" (resource `auth-sessions`, granted to every group): the user's open sessions
 * with remote logout, and their API tokens with revocation. Only the user's own sessions and
 * tokens can be ended; the check is made on the server for every action.
 */
final class SessionsController extends BackendController
{
    public function index(): string
    {
        $user    = $this->currentUser();
        $tracker = new SessionTracker();
        $current = $tracker->current();
        $rows    = [];

        foreach ($tracker->activeFor((int) $user->id) as $row) {
            $rows[] = $row + [
                'device'  => DeviceName::fromUserAgent($row['user_agent'] ?? null),
                'current' => $current !== null && (int) $current['id'] === (int) $row['id'],
            ];
        }

        $tokens = [];

        foreach ($user->accessTokens() as $token) {
            $tokens[] = [
                'id'        => (int) $token->id,
                'name'      => (string) $token->name,
                'personal'  => ($token->secret2 ?? null) === PasswordChanger::PERSONAL_TOKEN,
                'created'   => $token->created_at?->format('Y-m-d H:i:s'),
                'last_used' => $token->last_used_at?->format('Y-m-d H:i:s'),
            ];
        }

        $this->breadcrumb(lang('Auth.profile.title'), site_url('backend/profile'))->breadcrumb(lang('Auth.sessions.title'));

        return $this->render('Modules\Auth\Views\sessions', [
            'title'     => lang('Auth.sessions.title'),
            'icon'      => 'bi bi-laptop',
            'sessions'  => $rows,
            'tokens'    => $tokens,
            'canRevoke' => $this->can('auth-sessions.revoke'),
        ]);
    }

    public function revoke(int $id): RedirectResponse
    {
        $user    = $this->currentUser();
        $tracker = new SessionTracker();
        $current = $tracker->current();

        if ($current !== null && (int) $current['id'] === $id) {
            return $this->back('error', lang('Auth.sessions.useLogout'));
        }

        if (! $tracker->revoke((int) $user->id, $id)) {
            return $this->back('error', lang('Auth.sessions.notFound'));
        }

        security_log('Auth', 'session_revoked', ['resource' => 'auth-sessions', 'record_id' => $id]);

        return $this->back('success', lang('Auth.sessions.revoked'));
    }

    public function revokeOthers(): RedirectResponse
    {
        $user  = $this->currentUser();
        $ended = (new SessionTracker())->endOthers((int) $user->id, SessionTracker::END_REVOKED);

        security_log('Auth', 'sessions_revoked', ['resource' => 'auth-sessions', 'summary' => (string) $ended]);

        return $this->back('success', lang('Auth.sessions.revokedOthers', [$ended]));
    }

    public function revokeToken(int $id): RedirectResponse
    {
        $user  = $this->currentUser();
        $token = $user->getAccessTokenById($id);

        if ($token === null) {
            return $this->back('error', lang('Auth.sessions.tokenNotFound'));
        }

        $user->revokeAccessTokenBySecret((string) $token->secret);
        security_log('Auth', 'token_revoked', ['resource' => 'auth-sessions', 'record_id' => $id, 'summary' => (string) $token->name]);

        return $this->back('success', lang('Auth.sessions.tokenRevoked'));
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url('backend/profile/sessions'))->with($type, $message);
    }
}
