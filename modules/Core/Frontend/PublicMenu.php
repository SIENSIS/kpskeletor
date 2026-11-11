<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use Modules\Core\Menus\PublicMenuItem;

/**
 * A menu location of the public theme (`primary`, `footer`…) as an extension point: the helper
 * `menu('<location>')` triggers the event `publicMenu` with an instance of this class and a
 * module's listener (the Menus module) fills it with the items for the current visitor. Without a
 * listener the location is empty. Listeners must check that their module is enabled.
 */
final class PublicMenu
{
    public const EVENT = 'publicMenu';

    /** @var list<PublicMenuItem>|null */
    private ?array $items = null;

    public function __construct(public readonly string $location, public readonly string $locale, public readonly string $currentUrl)
    {
    }

    /**
     * @param list<PublicMenuItem> $items
     */
    public function set(array $items): void
    {
        $this->items ??= $items;
    }

    /**
     * @return list<PublicMenuItem>
     */
    public function items(): array
    {
        return $this->items ?? [];
    }
}
