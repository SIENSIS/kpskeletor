<?php

declare(strict_types=1);

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\AuthPolicy;
use Modules\Auth\Services\TwoFactor;

/**
 * `force2fa` filter (`backend/*`): a user in a group whose policy is `required`
 * (the group's 2FA policy in the `groups` table) and without 2FA can only reach the 2FA set-up screens (and the
 * forced password change, which runs first) until they enable a method.
 */
final class ForceTwoFactor implements FilterInterface
{
    /** Back-office paths reachable before 2FA is set up. */
    private const ALLOWED = ['backend/profile/two-factor', 'backend/profile/password'];

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth('session')->user();

        if (! $user instanceof User || ! $request instanceof IncomingRequest) {
            return null;
        }

        $path = trim($request->getPath(), '/');

        foreach (self::ALLOWED as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed . '/')) {
                return null;
            }
        }

        if (! (new AuthPolicy())->twoFactorRequiredFor($user->getGroups()) || (new TwoFactor())->enabled((int) $user->id)) {
            return null;
        }

        if (str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(403)->setJSON(['error' => lang('Auth.twoFactor.required')]);
        }

        // The set-up page explains why (no flash message: it would repeat it).
        return redirect()->to(site_url('backend/profile/two-factor'));
    }

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
