<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

use CodeIgniter\Shield\Entities\User;

/**
 * Extension point of the generated back-office menu (docs/menus.md, "Customisation").
 *
 * `MenuBuilder` triggers the event `backendMenu` with an instance of this class after reading the
 * manifests' entries and before filtering them by permission. A listener (the Menus module) may
 * replace the definitions: retouch (label, icon, order), relocate (parent, zone), hide, and add
 * custom groups and links. Whatever a listener does, every entry is then filtered by its own
 * permission: a layer can hide or move an entry, never grant it.
 *
 * Definition keys besides those of `MenuDefinitions` (all optional):
 * - `kind`: `route` (default: route + permission), `group` (a heading without a link) or `link`;
 * - `href`, `target` (`_self`|`_blank`): a `link`'s address (an internal path relative to the
 *   site URL, or an absolute `https://`/`http://` URL) and where it opens;
 * - `permissions` (all of them required), `anyPermissions` (one of them, e.g. the `can:` filter
 *   of the route the link points to) and `groups` (any of them; empty = everyone): a `link`'s
 *   visibility;
 * - `labels`: locale => text, replacing the language key `label`;
 * - `hidden`: true hides the entry and its children.
 *
 * Listeners must check that their module is enabled (a disabled module's `Config/Events.php` is
 * still discovered). The result is cached per group set like the rest of the menu.
 */
final class BackendMenuLayer
{
    public const EVENT = 'backendMenu';

    /**
     * @param array<string, array<string, mixed>> $definitions key => definition
     */
    public function __construct(public readonly User $user, private array $definitions)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     */
    public function replace(array $definitions): void
    {
        $this->definitions = $definitions;
    }
}
