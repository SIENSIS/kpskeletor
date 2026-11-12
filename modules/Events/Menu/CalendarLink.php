<?php

declare(strict_types=1);

namespace Modules\Events\Menu;

use Modules\Core\Menus\MenuLinkType;
use Modules\Events\Services\EventRepository;

/**
 * Menu link type "Calendar" offered to the public menu editor (`$menuLinks`): the public calendar
 * with every category (`all`) or filtered by one category (its slug). Hidden while the public
 * calendar is off (`Config\Calendar::$publicCalendar`).
 */
final class CalendarLink implements MenuLinkType
{
    public const ALL = 'all';

    public function key(): string
    {
        return 'events.calendar';
    }

    public function label(): string
    {
        return lang('Events.menuLink.calendar');
    }

    /**
     * @return array<string, string> `all` or a category slug => label
     */
    public function options(): array
    {
        $options = [self::ALL => lang('Events.menuLink.allCategories')];

        foreach ((new EventRepository())->categories() as $category) {
            $options[$category['slug']] = $category['name'];
        }

        return $options;
    }

    public function url(string $value): ?string
    {
        if (! (bool) config(\Modules\Events\Config\Calendar::class)->publicCalendar || ! array_key_exists($value, $this->options())) {
            return null;
        }

        return $value === self::ALL ? site_url('calendar') : site_url('calendar') . '?category=' . rawurlencode($value);
    }
}
