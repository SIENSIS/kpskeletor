<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use CodeIgniter\Shield\Entities\User;
use Config\Database;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\EscalationException;
use Modules\Core\Permissions\EscalationGuard;
use Modules\Core\Permissions\GrantManager;
use Modules\Core\Permissions\ImpliedPermissions;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;
use Throwable;

/**
 * The permission matrix (docs/specification.md, "RBAC" › "Permission matrix screen";
 * docs/users.md): one block per module, one row per resource, one column per group, the atomic
 * operations as checkboxes and "all" as the resource wildcard (`<prefix>.*`).
 *
 * Saving only stores atomic catalogue names and resource wildcards, completed with their
 * implied permissions by `GrantManager`. Grants the screen does not show (other wildcards,
 * permissions of disabled modules) are kept as they are. Every group is checked against the
 * escalation rules before anything is written, and all groups are saved in one transaction.
 */
final class PermissionMatrix
{
    public function __construct(
        private readonly PermissionCatalogue $catalogue = new PermissionCatalogue(),
        private readonly GroupRegistry $groups = new GroupRegistry(),
    ) {
    }

    /**
     * The blocks of the screen: module => resource prefix => label and operations (operation =>
     * permission name and label key), enabled modules only.
     *
     * @return array<string, array<string, array{label: string, operations: array<string, array{name: string, label: string}>}>>
     */
    public function blocks(): array
    {
        $enabled = array_flip($this->catalogue->enabledNames());
        $blocks  = [];

        foreach ($this->catalogue->stored() as $name => $entry) {
            if (! isset($enabled[$name])) {
                continue;
            }

            $prefix = explode('.', $name, 2)[0];
            $blocks[$entry['module']][$prefix]['label'] ??= self::resourceLabel($entry['module'], $entry['resource']);
            $blocks[$entry['module']][$prefix]['operations'][$entry['operation']] = ['name' => $name, 'label' => $entry['description']];
        }

        ksort($blocks);

        if (isset($blocks['Core'])) {
            $blocks = ['Core' => $blocks['Core']] + $blocks;
        }

        return $blocks;
    }

    /**
     * The names the screen can tick: every atomic name and every resource wildcard shown.
     *
     * @return list<string>
     */
    public function editableNames(): array
    {
        $names = [];

        foreach ($this->blocks() as $resources) {
            foreach ($resources as $prefix => $resource) {
                $names[] = $prefix . '.*';

                foreach ($resource['operations'] as $operation) {
                    $names[] = $operation['name'];
                }
            }
        }

        return $names;
    }

    /**
     * Current grants by group (every group of the `groups` table).
     *
     * @return array<string, list<string>>
     */
    public function grants(): array
    {
        $all    = $this->catalogue->allGrants();
        $grants = [];

        foreach (array_keys($this->groups->all()) as $group) {
            $grants[$group] = $all[$group] ?? [];
        }

        return $grants;
    }

    /**
     * Whether `$actor` may change the grants of `$group` at all.
     */
    public function canEditGroup(User $actor, string $group): bool
    {
        return (new EscalationGuard($this->catalogue))->canChangeGroupGrants($actor, $group);
    }

    /**
     * Saves the posted matrix atomically.
     *
     * @param array<string, mixed> $posted group => list of ticked names (atomic or `<prefix>.*`);
     *                                     groups that are not posted keep their grants
     *
     * @return list<string> The groups whose grants changed
     *
     * @throws EscalationException when a group cannot be changed by the actor or a name is invalid (nothing is saved)
     */
    public function save(User $actor, array $posted): array
    {
        $guard    = new EscalationGuard($this->catalogue);
        $editable = array_flip($this->editableNames());
        $current  = $this->grants();
        $planned  = [];

        foreach ($current as $group => $grants) {
            if (! array_key_exists($group, $posted) || ! $guard->canChangeGroupGrants($actor, $group)) {
                // Not on the form (each editable column posts an empty marker) or read-only for
                // this actor: unchanged, whatever was posted for it.
                continue;
            }

            $ticked = [];

            foreach ((array) $posted[$group] as $name) {
                if ($name === '') {
                    continue;
                }

                if (! is_string($name) || ! isset($editable[$name])) {
                    throw new EscalationException(lang('Core.rbac.unknownPermission', [is_string($name) ? $name : '?']));
                }

                $ticked[] = $name;
            }

            // Grants the screen does not show are kept.
            $kept = array_values(array_filter($grants, static fn (string $grant): bool => ! isset($editable[$grant])));
            $next = ImpliedPermissions::complete([...$kept, ...$ticked], ImpliedPermissions::operationsOf($this->catalogue->stored()));

            sort($grants);

            if ($next === $grants) {
                continue;
            }

            $added = array_values(array_diff($next, $grants));

            if (! $guard->canGrant($actor, $added)) {
                throw new EscalationException(lang('Core.rbac.cannotGrant', [$group]));
            }

            $planned[$group] = $next;
        }

        if ($planned === []) {
            return [];
        }

        $db = Database::connect();
        $db->transException(true)->transStart();

        try {
            $manager = new GrantManager($this->catalogue);

            foreach ($planned as $group => $next) {
                $manager->setGroupGrants($actor, $group, $next);
            }

            $db->transComplete();
        } catch (Throwable $e) {
            $db->transRollback();
            // Shield's settings were written inside the rolled-back transaction: write them again.
            (new ShieldMatrix($this->catalogue))->write();

            throw $e;
        }

        return array_keys($planned);
    }

    /**
     * Label of a resource (`<Module>.res.<resource>` by convention), or its name.
     */
    public static function resourceLabel(string $module, string $resource): string
    {
        $key   = $module . '.res.' . $resource;
        $label = lang($key);

        return $label === $key ? $resource : $label;
    }
}
