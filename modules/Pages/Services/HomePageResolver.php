<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Frontend\HomePage;

/**
 * The page chosen as the public home page (`Pages.homePage`: the page's path, e.g. `inici`).
 * Shown at `/` when it exists and the visitor may read it; otherwise the theme's default home.
 */
final class HomePageResolver
{
    /**
     * The id of the home page (whoever reads it), or null when none is set or it does not exist.
     */
    public function id(): ?int
    {
        $path = trim((string) setting('Pages.homePage'));

        return $path === '' ? null : (new PageResolver())->resolve($path, service('request')->getLocale());
    }

    public function answer(HomePage $home): void
    {
        $id = $this->id();

        if ($id === null) {
            return;
        }

        $user = auth()->loggedIn() ? auth()->user() : null;

        if (! PageVisibility::canRead($id, $user)) {
            return;
        }

        $page = (new PageRepository())->find($id);

        if ($page !== null) {
            if ($page['visibility'] !== 'public') {
                service('response')->setHeader('Cache-Control', 'private, no-store');
            }

            $home->set((new PageRenderer())->render($page, $user, true));
        }
    }
}
