<?php

declare(strict_types=1);

namespace Modules\Core\Notifications;

use Closure;
use CodeIgniter\Shield\Entities\User;

/**
 * The language a user reads notifications in (emails are rendered in it when queued).
 * The Core does not know where users keep their language: the Auth module registers a resolver
 * (its `Config/Events.php`); without one, the application's default locale is used.
 */
final class RecipientLocale
{
    /** @var (Closure(User): ?string)|null */
    private static ?Closure $resolver = null;

    /**
     * @param (Closure(User): ?string)|null $resolver
     */
    public static function resolveWith(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function for(User $user): string
    {
        $supported = config('App')->supportedLocales;
        $locale    = self::$resolver !== null ? (self::$resolver)($user) : null;

        return is_string($locale) && in_array($locale, $supported, true) ? $locale : config('App')->defaultLocale;
    }

    /**
     * Runs the callback with the language set to the locale, then restores it.
     *
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    public static function using(string $locale, Closure $callback): mixed
    {
        $language = service('language');
        $previous = $language->getLocale();
        $language->setLocale($locale);

        try {
            return $callback();
        } finally {
            $language->setLocale($previous);
        }
    }
}
