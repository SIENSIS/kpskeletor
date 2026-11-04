<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Frontend\Pwa as PwaService;

/**
 * Minimal PWA routes of the Core: `/manifest.webmanifest`, `/sw.js` (served from the root so the
 * service worker controls the whole site; it caches nothing) and `/pwa/icon-<192|512>.png`.
 * They answer 404 while the setting `Core.pwa` is off.
 */
final class Pwa extends BaseController
{
    public function manifest(): ResponseInterface
    {
        $this->assertEnabled();

        return $this->response
            ->setContentType('application/manifest+json')
            ->setHeader('Cache-Control', 'no-cache')
            ->setBody(json_encode((new PwaService(service('theme')))->manifest(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function serviceWorker(): ResponseInterface
    {
        $this->assertEnabled();

        return $this->response
            ->setContentType('text/javascript')
            ->setHeader('Cache-Control', 'no-cache')
            ->setHeader('Service-Worker-Allowed', '/')
            ->setBody((string) file_get_contents(dirname(__DIR__) . '/Assets/pwa/sw.js'));
    }

    public function icon(string $size): ResponseInterface
    {
        $this->assertEnabled();

        if (! in_array((int) $size, PwaService::SIZES, true) || (string) (int) $size !== $size) {
            throw PageNotFoundException::forPageNotFound();
        }

        $path = (new PwaService(service('theme')))->icon((int) $size);

        return $this->response
            ->setContentType('image/png', '')
            ->setHeader('Cache-Control', 'public, max-age=86400')
            ->setBody((string) file_get_contents($path));
    }

    private function assertEnabled(): void
    {
        if (! PwaService::enabled()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
