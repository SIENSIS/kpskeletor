<?php

declare(strict_types=1);

namespace Modules\News\Menu;

use Modules\Core\Menus\MenuLinkType;
use Modules\News\Services\NewsRepository;

/**
 * Menu link type "News category" offered to the public menu editor (`$menuLinks`, the Core's
 * `MenuLinkType`): a label, the choices
 * (active categories, by slug) and the public URL of a choice.
 */
final class CategoryLink implements MenuLinkType
{
    /** Stable key stored in menu items. */
    public function key(): string
    {
        return 'news.category';
    }

    public function label(): string
    {
        return lang('News.menuLink.category');
    }

    /**
     * @return array<string, string> slug => category name
     */
    public function options(): array
    {
        $options = [];

        foreach ((new NewsRepository())->categories() as $category) {
            $options[$category['slug']] = $category['name'];
        }

        return $options;
    }

    /**
     * The URL of a category, or null when it no longer exists (the item is then hidden).
     */
    public function url(string $value): ?string
    {
        return array_key_exists($value, $this->options()) ? site_url('news/category/' . rawurlencode($value)) : null;
    }
}
