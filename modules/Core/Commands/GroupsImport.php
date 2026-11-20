<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Groups\GroupTransfer;
use Modules\Core\Permissions\EscalationException;

/**
 * php spark groups:import <file> [--grants] [--yes]
 *
 * Loads the groups of `groups:export` (D-42-04): creates the missing ones, updates the title,
 * description and 2FA policy of the existing ones and, with `--grants`, replaces their grants by
 * the exported permissions that exist here. Asks for confirmation (it changes privileges) unless
 * `--yes`; logged as `groups_imported` (and `grants_changed` per group).
 */
final class GroupsImport extends BaseCommand
{
    protected $group       = 'Permissions';
    protected $name        = 'groups:import';
    protected $description = 'Imports groups exported with groups:export (creates and updates them; with --grants, their permissions).';
    protected $usage       = 'groups:import <file> [--grants] [--yes]';

    /** @var array<string, string> */
    protected $arguments = ['file' => 'A file written by groups:export'];

    /** @var array<string, string> */
    protected $options = [
        '--grants' => 'Also replace the groups\' permissions by the exported ones (those that exist here)',
        '--yes'    => 'Do not ask for confirmation',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $file = trim((string) ($params[0] ?? ''));

        if ($file !== '' && ! is_file($file) && is_file(ROOTPATH . $file)) {
            $file = ROOTPATH . $file; // spark runs from public/
        }

        $json = $file !== '' && is_file($file) && filesize($file) <= 1_048_576 ? file_get_contents($file) : false;
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data) || ! db_connect()->tableExists('groups')) {
            CLI::error(is_array($data) ? lang('Core.rbac.notInstalled') : lang('Core.groups.transfer.invalidFile'));

            return EXIT_ERROR;
        }

        $grants = ModulesSync::option($params, 'grants') !== null;

        if (ModulesSync::option($params, 'yes') === null
            && CLI::prompt(lang($grants ? 'Core.groups.transfer.confirmGrants' : 'Core.groups.transfer.confirm', [count((array) ($data['groups'] ?? []))]), ['n', 'y']) !== 'y') {
            CLI::write(lang('Core.cancelled'));

            return EXIT_ERROR;
        }

        try {
            $report = (new GroupTransfer())->import($data, $grants);
        } catch (EscalationException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        foreach ($report['errors'] as $error) {
            CLI::error($error);
        }

        CLI::write(lang('Core.groups.transfer.imported', [implode(', ', $report['created']) ?: '—', implode(', ', $report['updated']) ?: '—']), 'green');

        if ($grants) {
            CLI::write(lang('Core.groups.transfer.grantsImported', [implode(', ', $report['grants']) ?: '—']));
        }

        foreach ($report['skippedPermissions'] as $skipped) {
            CLI::write(lang('Core.groups.transfer.skipped', [$skipped]), 'yellow');
        }

        return $report['errors'] === [] ? EXIT_SUCCESS : EXIT_ERROR;
    }
}
