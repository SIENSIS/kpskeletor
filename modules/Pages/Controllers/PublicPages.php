<?php

declare(strict_types=1);

namespace Modules\Pages\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Controllers\FrontendController;
use Modules\Pages\Services\HomePageResolver;
use Modules\Pages\Services\PageRenderer;
use Modules\Pages\Services\PageRepository;
use Modules\Pages\Services\PageResolver;
use Modules\Pages\Services\PageVisibility;

/**
 * The public pages at their nested slugs (`GET /about/team`; docs/pages.md):
 *
 * - not found, trashed, a draft or scheduled for later: the theme's 404;
 * - `public`: everyone;
 * - `private` / `groups` (**private pages**): a guest is sent to the login (and back after it);
 *   a signed-in user who may not read it gets the 404 (its existence is not revealed). They are
 *   sent with `noindex` and `Cache-Control: private, no-store`;
 * - the home page (Settings › Pages) redirects to `/`.
 */
final class PublicPages extends FrontendController
{
    /**
     * @param string ...$segments the path's segments (the router splits `about/team` on `/`)
     */
    public function show(string ...$segments): ResponseInterface|string
    {
        $id = (new PageResolver())->resolve(implode('/', $segments), $this->request->getLocale());

        if ($id === null || ! PageVisibility::isPublished($id)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $user = auth()->loggedIn() ? auth()->user() : null;

        if (! PageVisibility::canRead($id, $user)) {
            if ($user === null) {
                return $this->toLogin();
            }

            throw PageNotFoundException::forPageNotFound();
        }

        if ($id === (new HomePageResolver())->id()) {
            return redirect()->to(site_url('/'));
        }

        $page = (new PageRepository())->find($id);

        if ($page === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        if ($page['visibility'] !== 'public') {
            $this->response->setHeader('Cache-Control', 'private, no-store');
        }

        return (new PageRenderer())->render($page, $user);
    }

    /**
     * The login page, coming back here after a successful login (Shield's `beforeLoginUrl`).
     */
    private function toLogin(): RedirectResponse
    {
        session()->setTempdata('beforeLoginUrl', current_url(), 300);

        return redirect()->route('login');
    }
}
