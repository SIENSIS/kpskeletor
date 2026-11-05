<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use CodeIgniter\Exceptions\ConfigException;
use Config\App;

/**
 * The shared mask key of kpcrudy and kpapy (`app.maskKey`, `Config\App::$maskKey`): masked ids
 * in URLs and signed URLs. Both libraries' configs read it from here, so the same id has the
 * same code in a CRUD and in the API.
 *
 * Without it the libraries fall back to `encryption.key` or a random key file in `writable/`,
 * which would differ between servers and change when the key file is lost: production refuses
 * to boot (`assertBootable()`, run on `pre_system` by `Config/Events.php`).
 */
final class MaskKey
{
    /** The libraries refuse shorter keys. */
    public const MIN_LENGTH = 32;

    /**
     * The configured key ('' when not set).
     */
    public static function value(): string
    {
        return trim(config(App::class)->maskKey);
    }

    /**
     * Why the key cannot be used in an environment, or null when it can. Outside production an
     * empty key is allowed (the libraries fall back to their own keys); a short one never is.
     */
    public static function problem(string $key, string $environment = ENVIRONMENT): ?string
    {
        if ($key === '') {
            return $environment === 'production'
                ? 'app.maskKey is empty: set it in .env (64 random hex characters) before running in production.'
                : null;
        }

        if (strlen($key) < self::MIN_LENGTH) {
            return 'app.maskKey must be at least ' . self::MIN_LENGTH . ' characters long.';
        }

        return null;
    }

    /**
     * Throws when the application must not start with the configured key.
     *
     * @throws ConfigException
     */
    public static function assertBootable(?string $key = null, string $environment = ENVIRONMENT): void
    {
        $problem = self::problem($key ?? self::value(), $environment);

        if ($problem !== null) {
            throw new ConfigException($problem);
        }
    }

    /**
     * A new random key (64 hex characters).
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }
}
