<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\MenuEntry;

/**
 * Core widget "Quick links": shortcuts chosen and ordered per group (option `links`): entries of
 * the back-office menu, which each user sees only when they can open them, and free links
 * (`https://` sites, opened in a new tab, or internal paths) shown to every user of the group
 * (D-16-06). The widget needs no permission of its own.
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
                'type'    => 'links',
                'default' => [
                    ['menu' => 'Core.notifications'],
                    ['menu' => 'Activity.mine'],
                    ['menu' => 'api-personal-tokens'],
                    ['menu' => 'Users.users'],
                    ['menu' => 'settings'],
                ],
                'label'   => 'Core.widgets.quickLinks.links',
            ],
        ];
    }

    public function render(array $options): string
    {
        $user    = auth()->user();
        $visible = $user instanceof User ? self::flatten((new MenuBuilder())->tree($user)) : [];
        $links   = [];

        foreach ((array) $options['links'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (isset($item['menu'])) {
                $entry = $visible[$item['menu']] ?? null;

                if ($entry !== null && $entry->url() !== null) {
                    $links[] = ['label' => $entry->text(), 'icon' => $entry->icon, 'url' => (string) $entry->url(), 'external' => false];
                }

                continue;
            }

            $url      = (string) $item['url'];
            $external = ! str_starts_with($url, '/');
            $links[]  = [
                'label'    => (string) $item['label'],
                'icon'     => $external ? 'bi bi-box-arrow-up-right' : 'bi bi-link-45deg',
                'url'      => $external ? $url : site_url(ltrim($url, '/')),
                'external' => $external,
            ];
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
