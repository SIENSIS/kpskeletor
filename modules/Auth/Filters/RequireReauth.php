<?php

declare(strict_types=1);

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Auth\Services\Reauth;

/**
 * `reauth` filter (re-authentication, "sudo mode"): lets the request through only when the user
 * confirmed their password (and 2FA code) in this session within `Auth.reauthMinutes`.
 *
 * Otherwise: JSON requests get 401 with the confirmation URL; a GET is remembered and sent to
 * `auth/reauth`, which returns to it; other methods are never replayed: the user confirms and
 * repeats the action (they return to the page they came from when it is on this site).
 * Guests are sent to the login (default deny). Use it after `can:` on the route:
 * `['filter' => ['can:backup.restore', 'reauth']]`, or call `$this->requireReauth()` in a
 * `BackendController`.
 */
final class RequireReauth implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = auth('session')->user();

        if (! $user instanceof User) {
            return redirect()->to(site_url('login'));
        }

        $reauth = new Reauth();

        if ($reauth->confirmed($user)) {
            return null;
        }

        $url = site_url('auth/reauth');

        if ($request instanceof IncomingRequest && str_contains($request->getHeaderLine('Accept'), 'application/json')) {
            return service('response')->setStatusCode(401)->setJSON(['error' => lang('Auth.reauth.required'), 'reauth' => $url]);
        }

        if ($request instanceof IncomingRequest) {
            $reauth->rememberReturn($request->getMethod() === 'GET' ? self::currentPath($request) : self::refererPath($request));
        }

        return redirect()->to($url)->with('message', lang('Auth.reauth.required'));
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

    private static function currentPath(IncomingRequest $request): string
    {
        $query = $request->getUri()->getQuery();

        return '/' . ltrim($request->getPath(), '/') . ($query !== '' ? '?' . $query : '');
    }

    /**
     * The path of the Referer when it is a page of this site, else ''.
     */
    private static function refererPath(IncomingRequest $request): string
    {
        $referer = $request->getHeaderLine('Referer');
        $base    = rtrim(site_url(), '/');

        if ($referer === '' || ! str_starts_with($referer, $base . '/')) {
            return '';
        }

        $basePath = (string) parse_url($base, PHP_URL_PATH);
        $path     = (string) parse_url($referer, PHP_URL_PATH);
        $query    = (string) parse_url($referer, PHP_URL_QUERY);
        $path     = $basePath !== '' && str_starts_with($path, $basePath) ? substr($path, strlen($basePath)) : $path;

        return '/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : '');
    }
}
