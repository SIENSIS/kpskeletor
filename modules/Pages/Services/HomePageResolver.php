<?php

declare(strict_types=1);

namespace Modules\Pages\Services;

use Modules\Core\Frontend\HomePage;

/**
 * The page chosen as the public home page (`Pages.homePage`: the page id, chosen from a list in
 * Settings › Pages, D-20-01). Shown at `/` when it exists, is not trashed and the visitor may read
 * it; otherwise the theme's default home.
 */
final class HomePageResolver
{
    /**
     * The id of the home page (whoever reads it), or null when none is set or it does not exist.
     */
    public function id(): ?int
    {
        $value = setting('Pages.homePage');
        $value = is_scalar($value) ? trim((string) $value) : '';

        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        return db_connect()->table('pages')->where('id', (int) $value)->where('deleted_at', null)->countAllResults() === 1 ? (int) $value : null;
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
