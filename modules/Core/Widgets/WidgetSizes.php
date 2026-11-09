<?php

declare(strict_types=1);

namespace Modules\Core\Widgets;

/**
 * Widget sizes and their grid columns. Every widget takes the whole width on phones; from tablets
 * up the dashboard is a 12-column grid.
 */
final class WidgetSizes
{
    public const SMALL  = 'small';
    public const MEDIUM = 'medium';
    public const LARGE  = 'large';
    public const FULL   = 'full';

    public const ALL = [self::SMALL, self::MEDIUM, self::LARGE, self::FULL];

    private const COLUMNS = [
        self::SMALL  => 'col-12 col-md-6 col-xl-4',
        self::MEDIUM => 'col-12 col-xl-6',
        self::LARGE  => 'col-12 col-xl-8',
        self::FULL   => 'col-12',
    ];

    /** Grid classes of a size (unknown sizes: medium). */
    public static function columns(string $size): string
    {
        return self::COLUMNS[$size] ?? self::COLUMNS[self::MEDIUM];
    }

    /**
     * The allowed sizes of a widget (unknown names dropped; never empty).
     *
     * @return non-empty-list<string>
     */
    public static function allowed(WidgetInterface $widget): array
    {
        $sizes = array_values(array_intersect($widget->sizes(), self::ALL));

        return $sizes === [] ? [self::MEDIUM] : $sizes;
    }

    /** The size to use: the wanted one when allowed, otherwise the widget's default. */
    public static function pick(WidgetInterface $widget, ?string $wanted): string
    {
        $allowed = self::allowed($widget);

        return $wanted !== null && in_array($wanted, $allowed, true) ? $wanted : $allowed[0];
    }

    /** Translated label of a size. */
    public static function label(string $size): string
    {
        return lang('Core.widgets.size.' . $size);
    }
}
