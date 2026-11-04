<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * `can:<permission>[,<permission>…]`: lets the request through only when the logged-in user has
 * one of the permissions. Default deny: guests and requests without arguments get 403.
 *
 * The central permission resolver, the login redirect and the activity log are added by the
 * RBAC and authentication sessions.
 */
final class Can implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth()->user();

        if ($user !== null && $arguments !== null && $arguments !== []) {
            foreach ($arguments as $permission) {
                if ($user->can($permission)) {
                    return null;
                }
            }
        }

        return service('response')
            ->setStatusCode(403)
            ->setContentType('text/plain')
            ->setBody(lang('Core.forbidden'));
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
