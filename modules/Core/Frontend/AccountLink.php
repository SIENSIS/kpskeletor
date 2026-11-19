<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;
use Throwable;

/**
 * The account link of the public site (docs/themes.md, "Account link"): "Sign in" for guests,
 * "Administration" for signed-in users who can open the back office, "My account" otherwise.
 *
 * Built from route names and the generated back-office menu, never from the `backend` string or
 * a module name: a user "can open the back office" when the `sidebar` or `settings` zone of their
 * menu has an entry (the link goes to the first one, the dashboard in the skeleton); "My account"
 * goes to the first entry of their `user` zone (the profile). The menu is already filtered by
 * permission, so the link never shows a screen the user cannot open.
 */
final class AccountLink
{
    public const LOGIN_ROUTE = 'login';

    /** Zones of the back-office menu that mean "can open the back office". */
    private const BACKEND_ZONES = ['sidebar', 'settings'];

    public function __construct(private readonly MenuBuilder $menu = new MenuBuilder())
    {
    }

    /**
     * The link for a visitor (null = guest), or null when there is nothing to show.
     *
     * @return array{kind: 'login'|'backend'|'account', label: string, url: string, icon: string}|null
     */
    public function for(?User $user): ?array
    {
        if ($user === null) {
            $url = self::route(self::LOGIN_ROUTE);

            return $url === null ? null : ['kind' => 'login', 'label' => lang('Core.account.signIn'), 'url' => $url, 'icon' => 'bi bi-box-arrow-in-right'];
        }

        $tree = $this->menu->tree($user);

        foreach (self::BACKEND_ZONES as $zone) {
            $url = self::firstUrl($tree[$zone] ?? []);

            if ($url !== null) {
                return ['kind' => 'backend', 'label' => lang('Core.account.backOffice'), 'url' => $url, 'icon' => 'bi bi-speedometer2'];
            }
        }

        $url = self::firstUrl($tree['user'] ?? []);

        return $url === null ? null : ['kind' => 'account', 'label' => lang('Core.account.myAccount'), 'url' => $url, 'icon' => 'bi bi-person-circle'];
    }

    /**
     * The first internal link of a list of entries, depth first (groups have no link).
     *
     * @param list<MenuEntry> $entries
     */
    private static function firstUrl(array $entries): ?string
    {
        foreach ($entries as $entry) {
            if ($entry->path !== null) {
                return $entry->url();
            }

            $url = self::firstUrl($entry->children);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private static function route(string $name): ?string
    {
        try {
            $path = route_to($name);
        } catch (Throwable) {
            return null;
        }

        return is_string($path) ? site_url(ltrim($path, '/')) : null;
    }
}
