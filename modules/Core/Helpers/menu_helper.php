<?php

declare(strict_types=1);

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;

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
