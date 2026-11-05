<?php

declare(strict_types=1);

namespace Modules\Core\Permissions;

use CodeIgniter\Shield\Authorization\PermissionMatcher;

/**
 * Computes Shield's `AuthGroups.permissions` and `AuthGroups.matrix` from the permission
 * catalogue of the enabled modules and `group_permissions` (the source of truth), and writes them
 * with `setting()->set()` (decision D-00-19). Shield reads them through `setting()`, so its
 * `can()` and `addPermission()` see exactly the generated catalogue.
 *
 * Grants naming a permission of a disabled module, or a wildcard that matches none of the enabled
 * permissions, are left out of the matrix (they come back when the module is enabled again).
 * Called after every `modules:sync`, module enable/disable and grant change.
 */
final class ShieldMatrix
{
    public function __construct(private readonly PermissionCatalogue $catalogue = new PermissionCatalogue())
    {
    }

    /**
     * @return array{permissions: array<string, string>, matrix: array<string, list<string>>}
     */
    public function compute(): array
    {
        $enabled = $this->catalogue->enabledNames();
        $stored  = $this->catalogue->stored();
        $groups  = array_keys((array) setting('AuthGroups.groups'));

        $permissions = [];

        foreach ($enabled as $name) {
            $permissions[$name] = $stored[$name]['description'] ?? $name;
        }

        $matrix = [];
        $grants = $this->catalogue->allGrants();

        foreach ($groups as $group) {
            $matrix[(string) $group] = array_values(array_filter(
                $grants[$group] ?? [],
                static fn (string $grant): bool => self::effective($grant, $enabled),
            ));
        }

        return ['permissions' => $permissions, 'matrix' => $matrix];
    }

    /**
     * Writes the computed arrays into the settings Shield reads.
     */
    public function write(): void
    {
        $computed = $this->compute();

        setting()->set('AuthGroups.permissions', $computed['permissions']);
        setting()->set('AuthGroups.matrix', $computed['matrix']);
    }

    /**
     * Whether Shield's current settings agree with `group_permissions` and the catalogue.
     */
    public function inSync(): bool
    {
        $computed = $this->compute();

        return (array) setting('AuthGroups.permissions') === $computed['permissions']
            && (array) setting('AuthGroups.matrix') === $computed['matrix'];
    }

    /**
     * Whether a grant (atomic name or Shield wildcard) gives at least one of the names.
     *
     * @param list<string> $names
     */
    public static function effective(string $grant, array $names): bool
    {
        if (! str_contains($grant, '*')) {
            return in_array($grant, $names, true);
        }

        return self::matching($grant, $names) !== [];
    }

    /**
     * The names a grant gives.
     *
     * @param list<string> $names
     *
     * @return list<string>
     */
    public static function matching(string $grant, array $names): array
    {
        return array_values(array_filter($names, static fn (string $name): bool => PermissionMatcher::matches($name, [$grant])));
    }
}
