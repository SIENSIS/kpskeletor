<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Groups\GroupTransfer;

/**
 * php spark groups:export [<file>] [--grants]
 *
 * Writes the site's groups (names, titles, descriptions, 2FA policy; with `--grants` their
 * permissions) as JSON, to a file or the screen, to load them on another copy with
 * `groups:import` (D-42-04). No user or personal data is exported.
 */
final class GroupsExport extends BaseCommand
{
    protected $group       = 'Permissions';
    protected $name        = 'groups:export';
    protected $description = 'Exports the groups (names, titles, descriptions, 2FA policy, optionally their grants) as JSON; no users.';
    protected $usage       = 'groups:export [<file>] [--grants]';

    /** @var array<string, string> */
    protected $arguments = ['file' => 'File to write (default: the screen)'];

    /** @var array<string, string> */
    protected $options = ['--grants' => 'Also export each group\'s permissions'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        if (! db_connect()->tableExists('groups')) {
            CLI::error(lang('Core.rbac.notInstalled'));

            return EXIT_ERROR;
        }

        $data = (new GroupTransfer())->export(ModulesSync::option($params, 'grants') !== null);
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        $file = trim((string) ($params[0] ?? ''));

        if ($file === '') {
            CLI::write($json);

            return EXIT_SUCCESS;
        }

        if (! str_starts_with($file, '/')) {
            $file = ROOTPATH . $file; // spark runs from public/
        }

        if (file_put_contents($file, $json) === false) {
            CLI::error(lang('Core.groups.transfer.cannotWrite', [$file]));

            return EXIT_ERROR;
        }

        CLI::write(lang('Core.groups.transfer.exported', [count($data['groups']), clean_path($file)]), 'green');

        return EXIT_SUCCESS;
    }
}
