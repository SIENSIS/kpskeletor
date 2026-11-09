<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;

/**
 * Core widget "Quick links": shortcuts to back-office screens chosen per group (option `links`,
 * menu entry keys). Each user only sees the entries of the generated menu they can open, so the
 * widget needs no permission of its own.
 */
final class QuickLinks extends Widget
{
    public function id(): string
    {
        return 'core.quick-links';
    }

    public function title(): string
    {
        return lang('Core.widgets.quickLinks.title');
    }

    public function icon(): string
    {
        return 'bi bi-link-45deg';
    }

    public function sizes(): array
    {
        return [WidgetSizes::SMALL, WidgetSizes::MEDIUM, WidgetSizes::LARGE, WidgetSizes::FULL];
    }

    public function settings(): array
    {
        return [
            'links' => [
                'type'    => 'menu',
                'default' => ['Core.notifications', 'Activity.mine', 'api-personal-tokens', 'Users.users', 'settings'],
                'label'   => 'Core.widgets.quickLinks.links',
                'help'    => 'Core.widgets.quickLinks.linksHelp',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user    = auth()->user();
        $visible = $user instanceof User ? self::flatten((new MenuBuilder())->tree($user)) : [];
        $links   = [];

        foreach ((array) $options['links'] as $key) {
            $entry = is_string($key) ? ($visible[$key] ?? null) : null;

            if ($entry !== null && $entry->url() !== null) {
                $links[] = ['label' => $entry->text(), 'icon' => $entry->icon, 'url' => (string) $entry->url()];
            }
        }

        if ($links === []) {
            return $this->empty(lang('Core.widgets.quickLinks.empty'), 'bi bi-link-45deg');
        }

        return $this->view('Modules\Core\Views\widgets\quick_links', ['links' => $links]);
    }

    /**
     * Every visible entry of the menu tree by key.
     *
     * @param array<string, list<MenuEntry>> $tree
     *
     * @return array<string, MenuEntry>
     */
    private static function flatten(array $tree): array
    {
        $entries = [];
        $stack   = array_merge(...array_values($tree ?: [[]]));

        while ($stack !== []) {
            $entry                 = array_shift($stack);
            $entries[$entry->key]  = $entry;
            array_push($stack, ...$entry->children);
        }

        return $entries;
    }
}
