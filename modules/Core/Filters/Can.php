<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Theme\ErrorPage;

/**
 * `can:<permission>[,<permission>…]`: lets the request through only when the logged-in user holds
 * **one** of the permissions; `can:all,<permission>,<permission>…` requires **all** of them
 * (decision D-07-02). Every denial is a `forbidden` security entry of the activity log. Decided by the central `Authorization` (superadmin bypass, unknown or
 * disabled permissions refused). Default deny: guests and filters without a permission get 403.
 *
 * Every route under `backend/` has one (`RoutesSecurityTest`); guests of `backend/*` are sent
 * to the login by the `backend` chain before this filter runs.
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
        $arguments   = array_values(array_filter(array_map('trim', $arguments ?? []), static fn (string $a): bool => $a !== ''));
        $all         = ($arguments[0] ?? null) === 'all';
        $permissions = $all ? array_slice($arguments, 1) : $arguments;

        if ($permissions !== []) {
            $authorization = Authorization::current();

            if ($all ? $authorization->canAll(...$permissions) : $authorization->can(...$permissions)) {
                return null;
            }
        }

        security_log('Core', 'forbidden', [
            'resource' => self::resourceOf($permissions),
            'summary'  => $request instanceof IncomingRequest ? $request->getMethod() . ' /' . ltrim($request->getPath(), '/') : '',
            'changes'  => ['permissions' => $permissions, 'all' => $all],
        ]);

        $response = service('response')->setStatusCode(403);

        if ($request instanceof IncomingRequest && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return $response->setJSON(['error' => lang('Core.forbidden')]);
        }

        return $response->setContentType('text/html')->setBody(ErrorPage::render(403, lang('Core.forbidden')));
    }

    /**
     * The resource of the first permission (`news-posts.edit` → `news-posts`).
     *
     * @param list<string> $permissions
     */
    private static function resourceOf(array $permissions): string
    {
        return $permissions === [] ? '' : explode('.', $permissions[0], 2)[0];
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
