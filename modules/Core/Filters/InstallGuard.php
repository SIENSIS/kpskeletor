<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Required filter (`installguard`, before the global filters): while the application is not
 * configured (no `.env`, no `writable/install.lock`), every web request except the web
 * installer is redirected to `install`; nothing else can run without a database. Once it is
 * configured the filter does nothing (and the installer answers 404).
 */
final class InstallGuard implements FilterInterface
{
    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (service('installstate')->isConfigured()) {
            return null;
        }

        $path = $request instanceof IncomingRequest ? $request->getPath() : $request->getUri()->getPath();

        if (self::isInstallerPath($path)) {
            return null;
        }

        return service('response')->setStatusCode(302)->setHeader('Location', site_url('install'));
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

    public static function isInstallerPath(string $path): bool
    {
        $path = trim($path, '/');

        return $path === 'install' || str_starts_with($path, 'install/');
    }
}
