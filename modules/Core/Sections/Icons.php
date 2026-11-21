<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

/**
 * The names of the vendored Bootstrap Icons (`public/assets/vendor/bootstrap-icons`), read from
 * its stylesheet: the choices of an icon field. Stored values are `bi-<name>`; anything else is
 * refused, so an icon never carries other classes.
 */
final class Icons
{
    public const PATTERN = '/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var list<string>|null */
    private static ?array $names = null;

    /**
     * Every icon class (`bi-alarm`, `bi-house`…), in the stylesheet's order.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        if (self::$names !== null) {
            return self::$names;
        }

        $file = FCPATH . 'assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css';
        $css  = is_file($file) ? (string) file_get_contents($file) : '';

        preg_match_all('/\.(bi-[a-z0-9]+(?:-[a-z0-9]+)*)::?before/', $css, $matches);

        return self::$names = array_values(array_unique($matches[1]));
    }

    public static function exists(string $icon): bool
    {
        return preg_match(self::PATTERN, $icon) === 1 && in_array($icon, self::all(), true);
    }
}
