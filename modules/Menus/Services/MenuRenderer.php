<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Menus\MenuBuilder;
use Modules\Core\Menus\PublicMenuItem;
use Modules\Core\Permissions\Authorization;

/**
 * Renders the public menu placed in a theme location for a visitor (`menu('primary')`): items
 * filtered by visibility (everyone, guests, signed-in users, groups) and by the permissions of the
 * route they point to, labels in the visitor's language, items whose target is gone (a deleted
 * page, a disabled module) hidden with their children unless a child is visible (then the item is
 * a heading). The tree is cached per menu, language and group set (scope `menus`, five minutes so
 * a page that gets published or unpublished shows up); the active item (the current URL, else
 * the longest item URL that is a parent of it) is marked on each request.
 */
final class MenuRenderer
{
    /** Seconds: page link types depend on the pages' publication, which changes on its own. */
    public const TTL = 300;

    public function __construct(
        private readonly MenuRepository $menus = new MenuRepository(),
        private readonly CoreCache $cache = new CoreCache(),
        private readonly ?LinkTarget $targets = null,
    ) {
    }

    /**
     * @return list<PublicMenuItem>
     */
    public function items(string $location, ?User $user, string $locale, string $currentUrl = ''): array
    {
        $menuId = $this->menus->forLocation($location);

        if ($menuId === null) {
            return [];
        }

        $key  = 'public.' . $menuId . '.' . $locale . '.' . ($user === null ? 'guest' : MenuBuilder::cacheKey($user));
        $data = $this->cache->remember('menus', $key, fn (): array => $this->build($menuId, $user, $locale), self::TTL);

        $items = array_map(static fn (array $item): PublicMenuItem => PublicMenuItem::fromArray($item), $data);

        if ($currentUrl !== '') {
            self::markActive($items, $currentUrl);
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function build(int $menuId, ?User $user, string $locale): array
    {
        $items    = $this->menus->items($menuId);
        $auth     = Authorization::for($user);
        $groupIds = $user === null ? [] : \Modules\Core\Content\Audience::groupIds($user);
        $targets  = $this->targets ?? new LinkTarget();
        $children = [];

        foreach ($items as $id => $item) {
            $children[$item['parent_id'] ?? 0][] = $id;
        }

        $node = function (int $id, int $depth) use (&$node, $items, $children, $auth, $user, $groupIds, $targets, $locale): ?PublicMenuItem {
            $item = $items[$id];

            if ($depth > MenuRepository::MAX_DEPTH || ! self::visibleTo($item, $user, $groupIds)) {
                return null;
            }

            $visible = [];

            foreach ($children[$id] ?? [] as $childId) {
                $child = $node($childId, $depth + 1);

                if ($child !== null) {
                    $visible[] = $child;
                }
            }

            $target = $targets->resolve($item['type'], $item['value']);

            if ($target !== null && (($target['all'] !== [] && ! $auth->canAll(...$target['all'])) || ($target['any'] !== [] && ! $auth->can(...$target['any'])))) {
                $target = null; // the visitor would get a 403
            }

            if ($target === null && $visible === []) {
                return null;
            }

            return new PublicMenuItem(
                $id,
                MenuRepository::label($item['labels'], $locale),
                $target['href'] ?? null,
                $item['icon'],
                $item['target'] === '_blank' ? '_blank' : '_self',
                $item['css_class'],
                $visible,
            );
        };

        $tree = [];

        foreach ($children[0] ?? [] as $id) {
            $item = $node($id, 1);

            if ($item !== null) {
                $tree[] = $item->toArray();
            }
        }

        return $tree;
    }

    /**
     * @param array{visibility: string, groups: list<int>} $item
     * @param list<int>                                    $groupIds
     */
    private static function visibleTo(array $item, ?User $user, array $groupIds): bool
    {
        return match ($item['visibility']) {
            'all'    => true,
            'guests' => $user === null,
            'users'  => $user !== null,
            'groups' => $user !== null && array_intersect($item['groups'], $groupIds) !== [],
            default  => false,
        };
    }

    /**
     * Marks the item of the current URL active and its ancestors open.
     *
     * @param list<PublicMenuItem> $items
     */
    private static function markActive(array $items, string $currentUrl): void
    {
        $current   = self::normalise($currentUrl);
        $best      = [];
        $bestScore = 0;

        $walk = static function (array $items, array $ancestors) use (&$walk, &$best, &$bestScore, $current): void {
            foreach ($items as $item) {
                $trail = [...$ancestors, $item];
                $url   = $item->url === null ? null : self::normalise($item->url);
                $score = 0;

                if ($url !== null && $url === $current) {
                    $score = PHP_INT_MAX;
                } elseif ($url !== null && parse_url($url, PHP_URL_PATH) !== null && trim((string) parse_url($url, PHP_URL_PATH), '/') !== '' && str_starts_with($current, $url . '/')) {
                    $score = strlen($url);
                }

                if ($score > $bestScore) {
                    $best      = $trail;
                    $bestScore = $score;
                }

                $walk($item->children, $trail);
            }
        };
        $walk($items, []);

        foreach ($best as $i => $item) {
            $item->open   = true;
            $item->active = $i === count($best) - 1;
        }
    }

    /** A URL without query, fragment, `index.php` or trailing slash, for comparisons. */
    private static function normalise(string $url): string
    {
        $url = (string) preg_replace('/[?#].*$/', '', $url);
        $url = str_replace('/index.php', '', $url);

        return rtrim($url, '/');
    }
}
