<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Permissions\PermissionCatalogue;
use Modules\Core\Permissions\ShieldMatrix;

/**
 * php spark permissions:list [--module=<Name>] [--group=<group>]
 */
final class PermissionsList extends BaseCommand
{
    protected $group       = 'Permissions';
    protected $name        = 'permissions:list';
    protected $description = 'Lists the permission catalogue with the groups that hold each permission.';
    protected $usage       = 'permissions:list [--module=<Name>] [--group=<group>]';
    protected $options     = [
        '--module' => 'Only the permissions of this module.',
        '--group'  => 'Only the permissions this group holds (superadmins hold every one).',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        if (! db_connect()->tableExists('permissions')) {
            CLI::error(lang('Core.rbac.notInstalled'));

            return EXIT_ERROR;
        }

        $module    = ModulesSync::option($params, 'module');
        $group     = ModulesSync::option($params, 'group');
        $catalogue = new PermissionCatalogue();
        $enabled   = $catalogue->enabledNames();
        $grants    = $catalogue->allGrants();
        $groups    = array_keys((array) setting('AuthGroups.groups'));
        $rows      = [];

        foreach ($catalogue->stored() as $name => $entry) {
            if (is_string($module) && strcasecmp($module, $entry['module']) !== 0) {
                continue;
            }

            $holders = [];

            foreach ($groups as $g) {
                if ($g === 'superadmin' || array_filter($grants[$g] ?? [], static fn (string $grant): bool => ShieldMatrix::matching($grant, [$name]) !== []) !== []) {
                    $holders[] = $g;
                }
            }

            if (is_string($group) && ! in_array($group, $holders, true)) {
                continue;
            }

            $rows[] = [
                $name,
                $entry['module'],
                lang($entry['description']),
                in_array($name, $enabled, true) ? lang('Core.list.yes') : lang('Core.list.no'),
                implode(', ', $holders),
            ];
        }

        if ($rows === []) {
            CLI::write(lang('Core.rbac.listEmpty'));

            return EXIT_SUCCESS;
        }

        CLI::table($rows, [
            lang('Core.rbac.permission'),
            lang('Core.list.module'),
            lang('Core.rbac.description'),
            lang('Core.list.enabled'),
            lang('Core.rbac.groups'),
        ]);

        return EXIT_SUCCESS;
    }
}
