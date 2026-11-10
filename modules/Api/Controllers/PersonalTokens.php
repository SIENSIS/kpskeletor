<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Entities\User;
use InvalidArgumentException;
use Modules\Api\Services\PersonalTokens as Tokens;
use Modules\Core\Controllers\BackendController;

/**
 * "My API tokens" (`api.tokens`): the user's tokens with name, scopes, expiry, last use and
 * address; creating one needs re-authentication (`reauth` filter) and shows the secret once;
 * revoking works only on the user's own tokens (checked on the server).
 */
final class PersonalTokens extends BackendController
{
    public function index(): string
    {
        $user    = $this->currentUser();
        $service = new Tokens();

        // Shown once: removed from the session as soon as it is read.
        $newToken = session()->getFlashdata('apiNewToken');
        session()->remove('apiNewToken');

        $this->breadcrumb(lang('Auth.profile.title'), site_url('backend/profile'))->breadcrumb(lang('Api.personal.title'));

        return $this->render('Modules\Api\Views\tokens\personal', [
            'title'       => lang('Api.personal.title'),
            'icon'        => 'bi bi-key',
            'tokens'      => $service->list($user),
            'scopes'      => $service->availableScopes($user),
            'days'        => Tokens::DAYS,
            'defaultDays' => Tokens::DEFAULT_DAYS,
            'newToken'    => is_string($newToken) ? $newToken : null,
        ]);
    }

    public function create(): RedirectResponse
    {
        $user   = $this->currentUser();
        $name   = $this->request->getPost('name');
        $scope  = $this->request->getPost('scope');
        $scopes = $scope === 'all' ? ['*'] : (array) ($this->request->getPost('scopes') ?? []);
        $days   = $this->request->getPost('days');

        try {
            $token = (new Tokens())->create($user, is_string($name) ? $name : '', array_values(array_filter($scopes, 'is_string')), is_numeric($days) ? (int) $days : 0);
        } catch (InvalidArgumentException $e) {
            return redirect()->to(site_url('backend/profile/api-tokens'))->withInput()->with('error', $e->getMessage());
        }

        session()->setFlashdata('apiNewToken', $token['token']);

        return redirect()->to(site_url('backend/profile/api-tokens'))->with('success', lang('Api.personal.created'));
    }

    public function revoke(int $id): RedirectResponse
    {
        if (! (new Tokens())->revoke($this->currentUser(), $id)) {
            return redirect()->to(site_url('backend/profile/api-tokens'))->with('error', lang('Api.personal.notFound'));
        }

        return redirect()->to(site_url('backend/profile/api-tokens'))->with('success', lang('Api.personal.revoked'));
    }
}
