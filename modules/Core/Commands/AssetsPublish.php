<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Frontend\AssetPublisher;

/**
 * php spark assets:publish [--watch] [--check]
 */
final class AssetsPublish extends BaseCommand
{
    protected $group       = 'Modules';
    protected $name        = 'assets:publish';
    protected $description = 'Publishes the assets of the Core, the modules and the themes to public/ and writes the asset manifest (hashes).';
    protected $usage       = 'assets:publish [--watch] [--check]';

    /** @var array<string, string> */
    protected $options = [
        '--watch' => 'Keep running and republish whenever a source file changes (development; Ctrl+C to stop).',
        '--check' => 'Change nothing; exit with an error when the published copies or the manifest are out of date.',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $publisher = new AssetPublisher();

        if (ModulesSync::option($params, 'check') !== null) {
            $problems = $publisher->check();

            if ($problems === []) {
                CLI::write(lang('Core.assets.upToDate'), 'green');

                return EXIT_SUCCESS;
            }

            CLI::error(lang('Core.assets.outdated', [count($problems)]));

            foreach ($problems as $problem) {
                CLI::write('  ' . $problem);
            }

            return EXIT_ERROR;
        }

        $this->report($publisher->publish(), true);

        if (ModulesSync::option($params, 'watch') === null) {
            return EXIT_SUCCESS;
        }

        CLI::write(lang('Core.assets.watching'), 'yellow');

        for (;;) {
            sleep(1);
            clearstatcache();

            if ($publisher->isStale()) {
                $this->report($publisher->publish(), false);
            }
        }
    }

    /**
     * @param array<string, int> $changes
     */
    private function report(array $changes, bool $always): void
    {
        unset($changes['manifest']);

        foreach ($changes as $owner => $count) {
            CLI::write(lang('Core.sync.assets', [$owner, $count]));
        }

        if ($changes === [] && $always) {
            CLI::write(lang('Core.assets.upToDate'), 'green');
        } elseif ($changes !== []) {
            CLI::write(lang('Core.assets.published', [date('H:i:s')]), 'green');
        }
    }
}
