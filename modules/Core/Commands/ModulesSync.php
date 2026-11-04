<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Demo\DemoDataException;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\SyncFailedException;

/**
 * php spark modules:sync [--prune] [--demo[=News,Events]]
 */
final class ModulesSync extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'modules:sync';
    protected $description = 'Discovers the modules, validates their manifests, runs migrations and seeders, and updates the registry, the permission catalogue, the default grants and the declarations. Idempotent.';
    protected $usage       = 'modules:sync [--prune] [--demo[=<Module,Module>]]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--prune' => 'Remove permissions that no manifest generates any more (asks for confirmation).',
        '--demo'  => 'Load the demo data of every enabled module, or of the listed modules (asks for confirmation).',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $prune = self::option($params, 'prune') !== null;
        $demo  = null;

        if (($value = self::option($params, 'demo')) !== null) {
            $demo = $value === true ? [] : array_values(array_filter(array_map('trim', explode(',', $value))));

            try {
                (new DemoDataManager())->assertAllowed();
            } catch (DemoDataException $e) {
                CLI::error($e->getMessage());

                return EXIT_ERROR;
            }

            if (CLI::prompt(lang('Core.demo.confirmLoad', [$demo === [] ? lang('Core.demo.allModules') : implode(', ', $demo)]), ['n', 'y']) !== 'y') {
                CLI::write(lang('Core.cancelled'));

                $demo = null;
            }
        }

        $confirm = static function (array $stale): bool {
            CLI::write(lang('Core.sync.pruneList', [implode(', ', $stale)]), 'yellow');

            return CLI::prompt(lang('Core.sync.pruneConfirm', [count($stale)]), ['n', 'y']) === 'y';
        };

        try {
            $report = (new ModuleSynchronizer())->sync($prune, $confirm, $demo);
        } catch (SyncFailedException $e) {
            CLI::error(lang('Core.sync.failed'));

            foreach ($e->errors as $error) {
                CLI::error('  - ' . $error);
            }

            return EXIT_ERROR;
        } catch (DemoDataException $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        foreach ($report->lines() as $line) {
            CLI::write($line);
        }

        CLI::write($report->hasChanges() || $report->demoLoaded !== [] ? lang('Core.sync.done') : lang('Core.sync.nothing'), 'green');

        return EXIT_SUCCESS;
    }

    /**
     * An option given as `--name` (true) or `--name=value` (string); null when absent.
     * CodeIgniter passes options in $params (null value for a flag) and in CLI::getOption().
     *
     * @param array<int|string, string|null> $params
     */
    public static function option(array $params, string $name): string|true|null
    {
        $value = array_key_exists($name, $params) ? ($params[$name] ?? true) : CLI::getOption($name);

        if ($value === null) {
            // CodeIgniter's parser keeps "--name=value" as an option named "name=value".
            foreach (array_merge(array_keys($params), array_keys(CLI::getOptions())) as $key) {
                if (is_string($key) && str_starts_with($key, $name . '=')) {
                    $value = substr($key, strlen($name) + 1);
                    break;
                }
            }
        }

        if ($value === null) {
            return null;
        }

        return $value === true || $value === '' ? true : (string) $value;
    }
}
