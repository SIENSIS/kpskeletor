<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Maintenance\MaintenanceMode;
use Modules\Core\Maintenance\MaintenancePage;
use Modules\Core\Permissions\Authorization;
use Throwable;

/**
 * Global filter (`maintenance`, first of the global filters): while maintenance mode is on,
 * every request answers 503 with `Retry-After` and the theme's maintenance page drawn without the
 * database (MaintenancePage; plain HTML if the theme fails), except
 * the paths the mode allows (MaintenanceMode::allows()). JSON requests get a JSON body.
 *
 * When an administrator turned it on by hand (the state's `bypass` flag, D-28-02), a signed-in
 * superadmin keeps using the site with a banner; the session is only read when the request
 * carries a session cookie. A restore or an update never sets the flag, so then nothing reads
 * the session or the database.
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

        $state = $mode->state() ?? ['bypass' => false, 'message' => ''];

        if ($state['bypass'] && self::superadminSignedIn($request)) {
            self::banner();

            return null;
        }

        $response = service('response')
            ->setStatusCode(503)
            ->setHeader('Retry-After', (string) self::RETRY_AFTER)
            ->setHeader('Cache-Control', 'no-store');

        if (str_contains($request->getHeaderLine('Accept'), 'application/json') || str_starts_with(trim($path, '/'), 'api/')) {
            return $response->setJSON(['error' => lang('Core.maintenance.title'), 'message' => $state['message'], 'retryAfter' => self::RETRY_AFTER]);
        }

        return $response->setBody(MaintenancePage::render($state['message']));
    }

    private static function superadminSignedIn(RequestInterface $request): bool
    {
        $cookie = config('Cookie')->prefix . config('Session')->cookieName;

        if (! $request instanceof IncomingRequest || (string) ($request->getCookie($cookie) ?? '') === '') {
            return false;
        }

        try {
            $user = auth('session')->user();

            return $user instanceof User && Authorization::isSuperadmin($user);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Reminds the superadmin that visitors see the maintenance page (top bar of the back office).
     */
    private static function banner(): void
    {
        try {
            service('theme')->regions()->add('topbar', view('Modules\Core\Views\maintenance_banner', [], ['saveData' => false]), 0);
        } catch (Throwable) {
            // No theme (e.g. an API request): nothing to show.
        }
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): ?ResponseInterface
    {
        return null;
    }
}
