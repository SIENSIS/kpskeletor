<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Frontend\PublicMenu;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;
use Modules\Core\Menus\PublicMenuItem;

if (! function_exists('menu')) {
    /**
     * The public menu placed in a location of the theme (`primary`, `footer`…) for the current
     * visitor and language, with the item of the current page marked active. Empty when no menu
     * is placed there (or the Menus module is disabled). Render it with the theme partial
     * `partials/public_menu`.
     *
     * @return list<PublicMenuItem>
     */
    function menu(string $location): array
    {
        $request = service('request');
        $locale  = $request instanceof IncomingRequest ? $request->getLocale() : config('App')->defaultLocale;
        $menu    = new PublicMenu($location, $locale, current_url());
        Events::trigger(PublicMenu::EVENT, $menu);

        return $menu->items();
    }
}

if (! function_exists('backend_menu')) {
    /**
     * The generated back-office menu of a zone (`sidebar`, `settings`, `user`…) for the logged-in
     * user, filtered by permission, with the entry of the current request marked active.
     *
     * @return list<MenuEntry>
     */
    function backend_menu(string $zone = 'sidebar'): array
    {
        [$path, $route] = backend_menu_request();

        return (new MenuBuilder())->zone($zone, backend_menu_user(), $path, $route);
    }
}

if (! function_exists('backend_menu_trail')) {
    /**
     * The menu entries from the root to the one of the current request (for breadcrumbs).
     *
     * @return list<MenuEntry>
     */
    function backend_menu_trail(): array
    {
        [$path, $route] = backend_menu_request();

        return (new MenuBuilder())->trail(backend_menu_user(), $path, $route);
    }
}

if (! function_exists('backend_menu_user')) {
    /**
     * @internal
     */
    function backend_menu_user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}

if (! function_exists('backend_menu_request')) {
    /**
     * The current path and route name.
     *
     * @internal
     *
     * @return array{0: string, 1: string|null}
     */
    function backend_menu_request(): array
    {
        $request = service('request');
        $path    = $request instanceof IncomingRequest ? $request->getPath() : '';
        $options = service('router')->getMatchedRouteOptions();
        $route   = is_array($options) && is_string($options['as'] ?? null) ? $options['as'] : null;

        return [$path, $route];
    }
}
