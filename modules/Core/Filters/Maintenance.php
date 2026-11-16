<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * Global filter (`maintenance`, first of the global filters): while maintenance mode is on,
 * every request answers 503 with `Retry-After` and a plain page that needs no database, except
 * the paths the mode allows (MaintenanceMode::allows()). JSON requests get a JSON body.
 */
final class Maintenance implements FilterInterface
{
    /** Seconds suggested to clients before trying again. */
    public const RETRY_AFTER = 60;

    /**
     * @param list<string>|null $arguments
     *
     * @return ResponseInterface|null
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $mode = new MaintenanceMode();

        if (is_cli() && ENVIRONMENT !== 'testing' || ! $mode->isOn()) {
            return null;
        }

        $path = method_exists($request, 'getPath') ? (string) $request->getPath() : (string) $request->getUri()->getPath();

        if ($mode->allows($path)) {
            return null;
        }

        $response = service('response')
            ->setStatusCode(503)
            ->setHeader('Retry-After', (string) self::RETRY_AFTER)
            ->setHeader('Cache-Control', 'no-store');

        if (str_contains($request->getHeaderLine('Accept'), 'application/json') || str_starts_with(trim($path, '/'), 'api/')) {
            return $response->setJSON(['error' => lang('Core.maintenance.title'), 'retryAfter' => self::RETRY_AFTER]);
        }

        return $response->setBody(view('Modules\Core\Views\errors\maintenance', [], ['debug' => false]));
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
