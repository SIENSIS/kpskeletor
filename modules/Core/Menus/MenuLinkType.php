<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

/**
 * A link type offered by a module to the menu editors (`$menuLinks` in the manifest; the Menus
 * module, docs/menus.md): a news category, a page, the events calendar…
 *
 * A menu item stores the type's `key()` and one of the `options()` keys as its value; the URL is
 * computed when the menu is built, so a renamed or moved target keeps working. `url()` returns
 * null when the target no longer exists or is not public: the item is then hidden.
 */
interface MenuLinkType
{
    /** Stable key stored in menu items (`<module>.<name>`, e.g. `pages.page`). */
    public function key(): string;

    /** Translated name of the type, shown in the editors. */
    public function label(): string;

    /**
     * The choices: value => translated label.
     *
     * @return array<int|string, string>
     */
    public function options(): array;

    /** The absolute URL of a value, or null when the target is gone (the item is hidden). */
    public function url(string $value): ?string;
}
