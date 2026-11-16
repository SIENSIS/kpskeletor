<?php

declare(strict_types=1);

namespace Modules\Updates\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Updates\Services\ReleaseChecker;

/**
 * php spark updates:check
 *
 * Checks the configured repositories for new versions now (like the daily task and the "check
 * now" button), stores the result for the page and the widget, and notifies new versions.
 */
final class UpdatesCheck extends BaseCommand
{
    protected $group       = 'Updates';
    protected $name        = 'updates:check';
    protected $description = 'Checks the application and modules repositories for new versions.';
    protected $usage       = 'updates:check';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $status = service('updatechecker')->check();
        $core   = is_array($status['core'] ?? null) ? $status['core'] : [];

        if (($core['repository'] ?? '') === '') {
            CLI::write(lang('Updates.notConfigured'), 'yellow');
        }

        $rows = [];

        foreach (['Core' => $core] + (is_array($status['modules'] ?? null) ? $status['modules'] : []) as $name => $item) {
            if (! is_array($item) || ($item['repository'] ?? '') === '') {
                continue;
            }

            $rows[] = [
                $name === 'Core' ? lang('Updates.application') : (string) $name,
                (string) ($item['installed'] ?? ''),
                is_string($item['error'] ?? null) ? lang('Updates.cli.error') : (is_array($item['latest'] ?? null) ? (string) ($item['latest']['version'] ?? '') : '—'),
                ($item['newer'] ?? false) === true ? lang('Updates.new') : '',
            ];
        }

        if ($rows !== []) {
            CLI::table($rows, [lang('Updates.module'), lang('Updates.installed'), lang('Updates.latest'), '']);
        }

        if (is_string($status['error'] ?? null)) {
            CLI::error(lang('Updates.msg.checkFailed', [$status['error']]));

            return EXIT_ERROR;
        }

        $newer = ReleaseChecker::newer($status);
        CLI::write($newer === [] ? lang('Updates.msg.upToDate') : lang('Updates.msg.available', [implode(', ', array_map(static fn (string $name, string $version): string => $name . ' ' . $version, array_keys($newer), $newer))]), $newer === [] ? 'green' : 'yellow');

        return EXIT_SUCCESS;
    }
}
