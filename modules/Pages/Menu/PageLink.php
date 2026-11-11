<?php

declare(strict_types=1);

namespace Modules\Pages\Menu;

use Modules\Pages\Services\HomePageResolver;
use Modules\Pages\Services\PageTree;
use Modules\Pages\Services\PageVisibility;

/**
 * Menu link type "Page" offered to the public menu editor (`$menuLinks`; the Menus module
 * arrives in session 21 and formalises the contract, D-17-05): a label, the choices (every page,
 * in tree order, by id) and the public URL of a choice (null when the page is not published or
 * no longer exists; the item is then hidden). Who may read the page is still checked by the
 * page itself.
 */
final class PageLink
{
    /** Stable key stored in menu items. */
    public function key(): string
    {
        return 'pages.page';
    }

    public function label(): string
    {
        return lang('Pages.menuLink.page');
    }

    /**
     * @return array<int, string> page id => title (indented by depth); the menu item stores the id as text
     */
    public function options(): array
    {
        return (new PageTree())->options(null, service('request')->getLocale());
    }

    public function url(string $value): ?string
    {
        if (! ctype_digit($value) || ! PageVisibility::isPublished((int) $value)) {
            return null;
        }

        if ((int) $value === (new HomePageResolver())->id()) {
            return site_url('/');
        }

        $path = (new PageTree())->path((int) $value, service('request')->getLocale());

        return $path === null ? null : site_url($path);
    }
}
