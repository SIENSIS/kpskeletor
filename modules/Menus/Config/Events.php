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
 *   the location, for the visitor;
 * - `moduleUninstalling` (the Core's Uninstaller, docs/installing-modules.md): the customisations of the
 *   uninstalled module's back-office entries and the public menu items of its link types are
 *   deleted (their children move up one level). Runs while Menus is disabled too.
 */

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Frontend\PublicMenu;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Menus\BackendMenuLayer;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Core\Packages\ModuleUninstalling;
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

Events::on(ModuleUninstalling::EVENT, static function (ModuleUninstalling $event): void {
    $db = db_connect();

    if ($event->module === 'Menus' || ! $db->tableExists('menu_items')) {
        return;
    }

    $removed = 0;

    if ($event->menuKeys !== []) {
        $db->table('backend_menu_customisations')->whereIn('entry_key', $event->menuKeys)->delete();
        $removed += $db->affectedRows();
        $db->table('backend_menu_customisations')->whereIn('parent', $event->menuKeys)->update(['parent' => null]);
        $db->table('backend_menu_links')->whereIn('parent', $event->menuKeys)->update(['parent' => null]);
    }

    if ($event->linkTypes !== []) {
        foreach ($db->table('menu_items')->select('id, parent_id')->whereIn('type', $event->linkTypes)->get()->getResultArray() as $item) {
            $db->table('menu_items')->where('parent_id', $item['id'])->update(['parent_id' => $item['parent_id']]);
            $db->table('menu_item_translations')->where('item_id', $item['id'])->delete();
            $db->table('menu_item_groups')->where('item_id', $item['id'])->delete();
            $db->table('menu_items')->where('id', $item['id'])->delete();
            $removed++;
        }

        $db->table('backend_menu_links')->whereIn('type', $event->linkTypes)->delete();
        $removed += $db->affectedRows();
    }

    if ($removed > 0) {
        (new CacheInvalidator())->invalidate(['menus']);
        $event->note(lang('Menus.uninstall.removed', [$removed]));
    }
});
