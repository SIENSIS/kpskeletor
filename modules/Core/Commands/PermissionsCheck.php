<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Permissions\PermissionAudit;

/**
 * php spark permissions:check
 */
final class PermissionsCheck extends BaseCommand
{
    protected $group       = 'Permissions';
    protected $name        = 'permissions:check';
    protected $description = 'Checks the manifests, the permission catalogue, the grants, Shield\'s matrix and the route filters (exit code 1 on errors).';
    protected $usage       = 'permissions:check';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $result = (new PermissionAudit())->run();

        foreach ($result['warnings'] as $warning) {
            CLI::write(lang('Core.rbac.warning') . ' ' . $warning, 'yellow');
        }

        foreach ($result['errors'] as $error) {
            CLI::error(lang('Core.rbac.error') . ' ' . $error);
        }

        if ($result['errors'] !== []) {
            CLI::error(lang('Core.rbac.checkFailed', [count($result['errors'])]));

            return EXIT_ERROR;
        }

        CLI::write(lang('Core.rbac.checkOk', [$result['permissions'], $result['routes']]), 'green');

        return EXIT_SUCCESS;
    }
}
