<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * Triggered (event `moduleUninstalling`) by the Uninstaller once the module is disabled and,
 * unless `keepData`, its migrations rolled back, before its folder is deleted. Modules that keep
 * data about other modules remove it here: Menus its menu customisations and link-type items,
 * Dashboard its widgets in the group layouts. The Core itself removes the permissions, grants,
 * settings, tasks, declarations and assets, and the calendar entries through the calendar
 * contract (`service('calendar')->purgeModule()`; the Events module cannot have a
 * `Config/Events.php`, D-00-04).
 *
 * Listeners run even when their own module is disabled (the data must still go), and must not
 * fail: errors are caught and reported by the Uninstaller.
 */
final class ModuleUninstalling
{
    public const EVENT = 'moduleUninstalling';

    /** @var list<string> */
    private array $notes = [];

    /**
     * @param list<string> $permissions Permission names the module generated
     * @param list<string> $menuKeys    Keys of its back-office menu entries (MenuDefinitions)
     * @param list<string> $linkTypes   Keys of its menu link types (e.g. "news.category")
     * @param list<string> $widgets     Ids of its dashboard widgets (e.g. "news.latest")
     */
    public function __construct(
        public readonly string $module,
        public readonly bool $keepData,
        public readonly array $permissions = [],
        public readonly array $menuKeys = [],
        public readonly array $linkTypes = [],
        public readonly array $widgets = [],
    ) {
    }

    /**
     * A line for the operator ("Events: 3 calendar entries removed").
     */
    public function note(string $line): void
    {
        $this->notes[] = $line;
    }

    /**
     * @return list<string>
     */
    public function notes(): array
    {
        return $this->notes;
    }
}
