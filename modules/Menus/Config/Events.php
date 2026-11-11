<?php

declare(strict_types=1);

/**
 * Events of the Menus module (discovered by CodeIgniter, also while the module is disabled, so
 * the listeners check it):
 *
 * - `backendMenu` (the Core's `MenuBuilder`, `BackendMenuLayer`): applies the customisations of
 *   everyone and of the user's groups, and adds the custom groups and links; every entry is
 *   then filtered by its permission by the Core;
 * - `publicMenu` (the helper `menu('<location>')`, `PublicMenu`): the items of the menu placed in
 *   the location, for the visitor.
 */

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Frontend\PublicMenu;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Menus\BackendMenuLayer;
use Modules\Core\Registry\ModuleState;
use Modules\Menus\Services\CustomisationApplier;
use Modules\Menus\Services\MenuRenderer;

Events::on(BackendMenuLayer::EVENT, static function (BackendMenuLayer $layer): void {
    if (! (new ModuleState())->isEnabled('Menus')) {
        return;
    }

    $scopes = CustomisationApplier::scopesFor($layer->user->getGroups() ?? [], array_keys((new GroupRegistry())->all()));
    $layer->replace((new CustomisationApplier())->apply($layer->definitions(), $scopes));
});

Events::on(PublicMenu::EVENT, static function (PublicMenu $menu): void {
    if (! (new ModuleState())->isEnabled('Menus')) {
        return;
    }

    try {
        $user = auth()->user();
    } catch (Throwable) {
        $user = null; // no authentication available (e.g. an error page early in the request): a guest's menu
    }

    try {
        $menu->set((new MenuRenderer())->items($menu->location, $user instanceof User ? $user : null, $menu->locale, $menu->currentUrl));
    } catch (DatabaseException) {
        // Not installed yet (the installer's and early error pages): the location stays empty.
    }
});
