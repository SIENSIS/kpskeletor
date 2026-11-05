<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Theme\ErrorPage;

/**
 * `can:<permission>[,<permission>…]`: lets the request through only when the logged-in user has
 * one of the permissions. Default deny: guests and requests without arguments get 403.
 * Superadmins bypass the check (docs/specification.md, "Groups").
 *
 * The central permission resolver and the activity log are added by the RBAC session; guests
 * of `backend/*` are sent to the login by Shield's `session` filter before this one runs.
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
            if ($user->inGroup('superadmin')) {
                return null;
            }

            foreach ($arguments as $permission) {
                if ($user->can($permission)) {
                    return null;
                }
            }
        }

        $response = service('response')->setStatusCode(403);

        if ($request instanceof IncomingRequest && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON(['error' => lang('Core.forbidden')]);
        }

        return $response->setContentType('text/html')->setBody(ErrorPage::render(403, lang('Core.forbidden')));
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
