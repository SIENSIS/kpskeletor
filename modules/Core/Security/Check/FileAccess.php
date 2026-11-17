<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use Closure;

/**
 * File modes for `security:check`: who may read or write a path. "The web user" is
 * `productioncheck.webUser` when set (computed from the owner, group and mode, POSIX), the
 * current process on a web request (the Security status page runs as the web user), and
 * unknown from the console otherwise (only "writable by everybody" is known then).
 */
final class FileAccess
{
    /**
     * @param (Closure(string): ?bool)|null $writable decides "writable by the web user" (tests)
     */
    public function __construct(
        private readonly string $webUser = '',
        private readonly ?Closure $writable = null,
        private readonly ?bool $cli = null,
    ) {
    }

    /**
     * Whether the web server user may write the path: null when it cannot be known.
     */
    public function writableByWebUser(string $path): ?bool
    {
        if ($this->writable !== null) {
            return ($this->writable)($path);
        }

        if (! file_exists($path)) {
            return false;
        }

        if ($this->webUser !== '' && function_exists('posix_getpwnam')) {
            $user = posix_getpwnam($this->webUser);

            if ($user === false) {
                return null;
            }

            return self::writableBy($path, (int) $user['uid'], (int) $user['gid'], $this->webUser);
        }

        if (! ($this->cli ?? is_cli())) {
            clearstatcache(true, $path);

            return is_writable($path);
        }

        return self::worldWritable($path) ? true : null;
    }

    /**
     * Whether the web user is known (configured, or this is a web request).
     */
    public function knowsWebUser(): bool
    {
        return $this->writable !== null || ($this->webUser !== '' && function_exists('posix_getpwnam') && posix_getpwnam($this->webUser) !== false) || ! ($this->cli ?? is_cli());
    }

    public static function mode(string $path): int
    {
        clearstatcache(true, $path);
        $perms = @fileperms($path);

        return $perms === false ? 0 : $perms & 0777;
    }

    public static function readableByOthers(string $path): bool
    {
        return (self::mode($path) & 0004) !== 0;
    }

    public static function worldWritable(string $path): bool
    {
        return (self::mode($path) & 0002) !== 0;
    }

    /**
     * Whether the mode grants more than $allowed (e.g. 0750 for folders: 0700 passes, 0755 not).
     */
    public static function looserThan(string $path, int $allowed): bool
    {
        return (self::mode($path) & ~$allowed & 0777) !== 0;
    }

    private static function writableBy(string $path, int $uid, int $gid, string $name): bool
    {
        if ($uid === 0) {
            return true;
        }

        clearstatcache(true, $path);
        $owner = @fileowner($path);
        $group = @filegroup($path);
        $mode  = self::mode($path);

        if ($owner === $uid) {
            return ($mode & 0200) !== 0;
        }

        if ($group === $gid || self::memberOf($group === false ? -1 : $group, $name)) {
            return ($mode & 0020) !== 0;
        }

        return ($mode & 0002) !== 0;
    }

    private static function memberOf(int $gid, string $name): bool
    {
        if ($gid < 0 || ! function_exists('posix_getgrgid')) {
            return false;
        }

        $group = posix_getgrgid($gid);

        return is_array($group) && in_array($name, $group['members'], true);
    }
}
