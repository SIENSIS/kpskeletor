<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Maintenance\MaintenanceMode;

/**
 * php spark app:maintenance on|off|status [--message="…"]
 *
 * Turns maintenance mode on by hand (the login paths stay open and signed-in superadmins keep
 * using the site, D-28-02) or off, e.g. after a failed update or restore left it on. Logged.
 */
final class AppMaintenance extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'app:maintenance';
    protected $description = 'Turns maintenance mode on or off, or shows its state.';
    protected $usage       = 'app:maintenance on|off|status [--message="…"]';
    protected $arguments   = ['action' => 'on, off or status'];
    protected $options     = ['--message' => 'Plain-text message shown on the maintenance page (with "on")'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $mode   = new MaintenanceMode();
        $action = (string) ($params[0] ?? 'status');

        switch ($action) {
            case 'on':
                $message = ModulesSync::option($params, 'message');
                $message = is_string($message) ? $message : '';
                $mode->enableByHand($message);
                security_log('Core', 'maintenance_on', ['resource' => 'maintenance', 'summary' => MaintenanceMode::cleanMessage($message), 'changes' => ['source' => 'console']]);
                CLI::write(lang('Core.maintenance.cliOnHand'), 'yellow');

                return EXIT_SUCCESS;

            case 'off':
                $state = $mode->state();
                $mode->disable();

                if ($state !== null) {
                    security_log('Core', 'maintenance_off', ['resource' => 'maintenance', 'summary' => $state['reason'], 'changes' => ['source' => 'console']]);
                }

                CLI::write(lang('Core.maintenance.cliOff'), 'green');

                return EXIT_SUCCESS;

            case 'status':
                $state = $mode->state();
                CLI::write($state === null ? lang('Core.maintenance.cliStatusOff') : lang('Core.maintenance.cliStatusOn', [$state['since'] . ' UTC', $state['reason']]));

                return EXIT_SUCCESS;
        }

        CLI::error(lang('Core.maintenance.cliUsage'));

        return EXIT_ERROR;
    }
}
