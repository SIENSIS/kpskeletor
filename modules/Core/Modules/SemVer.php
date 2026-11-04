<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

/**
 * Minimal semantic-versioning helper for module versions and `$requires` constraints.
 *
 * Supported constraints (Composer syntax subset): `*`, exact (`1.2.3`, `=1.2`), comparisons
 * (`>=1.0`, `>1.0`, `<=2`, `<2.0`, `!=1.1`), caret (`^1.2`), tilde (`~1.2`, `~1.2.3`), AND by a
 * space or comma, and OR by `||`.
 */
final class SemVer
{
    private const VERSION = '/^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?$/';

    /**
     * Whether a string is a full semantic version (MAJOR.MINOR.PATCH, optional pre-release).
     */
    public static function isVersion(string $version): bool
    {
        return preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/', $version) === 1;
    }

    /**
     * Whether a constraint string can be parsed.
     */
    public static function isConstraint(string $constraint): bool
    {
        try {
            self::parse($constraint);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Whether a version satisfies a constraint.
     *
     * @throws \InvalidArgumentException when the constraint cannot be parsed
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $version = self::normalise($version);

        foreach (self::parse($constraint) as $all) {
            $ok = true;

            foreach ($all as [$operator, $bound]) {
                if (! version_compare($version, $bound, $operator)) {
                    $ok = false;
                    break;
                }
            }

            if ($ok) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<list<array{0: string, 1: string}>> OR-groups of AND-ed [operator, version]
     */
    private static function parse(string $constraint): array
    {
        $constraint = trim($constraint);

        if ($constraint === '') {
            throw new \InvalidArgumentException('Empty constraint');
        }

        $groups = [];

        foreach (explode('||', $constraint) as $alternative) {
            $atoms = preg_split('/[\s,]+/', trim($alternative), -1, PREG_SPLIT_NO_EMPTY);

            if ($atoms === false || $atoms === []) {
                throw new \InvalidArgumentException('Empty alternative');
            }

            $all = [];

            foreach ($atoms as $atom) {
                foreach (self::atom($atom) as $pair) {
                    $all[] = $pair;
                }
            }

            $groups[] = $all;
        }

        return $groups;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private static function atom(string $atom): array
    {
        if ($atom === '*') {
            return [['>=', '0.0.0']];
        }

        if (preg_match('/^(\^|~|>=|<=|!=|>|<|=)?(.+)$/', $atom, $m) !== 1) {
            throw new \InvalidArgumentException("Invalid constraint {$atom}");
        }

        $operator = $m[1];
        $parts    = self::parts($m[2]);

        [$major, $minor, $patch, $pre, $given] = $parts;
        $base                                  = "{$major}.{$minor}.{$patch}" . ($pre !== '' ? "-{$pre}" : '');

        switch ($operator) {
            case '^':
                if ($major > 0 || $given === 1) {
                    $upper = ($major + 1) . '.0.0';
                } elseif ($minor > 0 || $given === 2) {
                    $upper = '0.' . ($minor + 1) . '.0';
                } else {
                    $upper = '0.0.' . ($patch + 1);
                }

                return [['>=', $base], ['<', $upper]];

            case '~':
                $upper = $given >= 3 ? "{$major}." . ($minor + 1) . '.0' : ($major + 1) . '.0.0';

                return [['>=', $base], ['<', $upper]];

            case '>=':
            case '>':
            case '<=':
            case '<':
                return [[$operator, $base]];

            case '!=':
                return [['!=', $base]];

            default: // exact, with partial versions matching the whole range (1.2 = 1.2.*)
                if ($given === 3) {
                    return [['==', $base]];
                }

                $upper = $given === 1 ? ($major + 1) . '.0.0' : "{$major}." . ($minor + 1) . '.0';

                return [['>=', $base], ['<', $upper]];
        }
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string, 4: int} major, minor, patch, pre-release, parts given
     */
    private static function parts(string $version): array
    {
        if (preg_match(self::VERSION, $version, $m) !== 1) {
            throw new \InvalidArgumentException("Invalid version {$version}");
        }

        $given = 1 + (int) (isset($m[2]) && $m[2] !== '') + (int) (isset($m[3]) && $m[3] !== '');

        return [(int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0), $m[4] ?? '', $given];
    }

    private static function normalise(string $version): string
    {
        [$major, $minor, $patch, $pre] = self::parts($version);

        return "{$major}.{$minor}.{$patch}" . ($pre !== '' ? "-{$pre}" : '');
    }
}
