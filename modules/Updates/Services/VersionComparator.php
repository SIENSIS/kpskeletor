<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

/**
 * Semantic versions of release tags (https://semver.org): `v1.2.3`, `1.2.3-beta.2+build.5`.
 * Precedence follows the specification: numeric MAJOR.MINOR.PATCH, a pre-release is lower than
 * its release, pre-release identifiers compared one by one (numbers numerically and lower than
 * text), build metadata ignored.
 */
final class VersionComparator
{
    private const PATTERN = '/\A(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9]\d*|\d*[A-Za-z-][0-9A-Za-z-]*))*))?(?:\+[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?\z/';

    /**
     * The version of a tag (a leading `v` is dropped), or null when it is not a semantic version.
     */
    public static function normalize(string $tag): ?string
    {
        $version = trim($tag);

        if ($version !== '' && ($version[0] === 'v' || $version[0] === 'V')) {
            $version = substr($version, 1);
        }

        return preg_match(self::PATTERN, $version) === 1 ? $version : null;
    }

    public static function isPrerelease(string $version): bool
    {
        $version = self::normalize($version);

        return $version !== null && preg_match(self::PATTERN, $version, $m) === 1 && ($m[4] ?? '') !== '';
    }

    /**
     * -1, 0 or 1 like the spaceship operator.
     *
     * @throws \InvalidArgumentException when one of them is not a semantic version
     */
    public static function compare(string $a, string $b): int
    {
        $left  = self::parts($a);
        $right = self::parts($b);

        for ($i = 0; $i < 3; $i++) {
            if ($left[$i] !== $right[$i]) {
                return $left[$i] <=> $right[$i];
            }
        }

        return self::comparePrerelease($left[3], $right[3]);
    }

    /**
     * Whether `$candidate` is a newer version than `$installed`.
     */
    public static function isNewer(string $candidate, string $installed): bool
    {
        return self::compare($candidate, $installed) > 0;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: list<string>}
     */
    private static function parts(string $version): array
    {
        $normal = self::normalize($version);

        if ($normal === null || preg_match(self::PATTERN, $normal, $m) !== 1) {
            throw new \InvalidArgumentException('Not a semantic version: ' . $version);
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3], ($m[4] ?? '') === '' ? [] : explode('.', $m[4])];
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function comparePrerelease(array $a, array $b): int
    {
        if ($a === [] || $b === []) {
            // A release is higher than any of its pre-releases.
            return count($b) <=> count($a);
        }

        foreach ($a as $i => $left) {
            if (! array_key_exists($i, $b)) {
                return 1;
            }

            $right = $b[$i];

            if ($left === $right) {
                continue;
            }

            $leftNumber  = ctype_digit($left);
            $rightNumber = ctype_digit($right);

            if ($leftNumber && $rightNumber) {
                return (int) $left <=> (int) $right;
            }

            if ($leftNumber !== $rightNumber) {
                return $leftNumber ? -1 : 1;
            }

            return strcmp($left, $right) <=> 0;
        }

        return count($a) < count($b) ? -1 : 0;
    }
}
