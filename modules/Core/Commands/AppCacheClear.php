<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Cache\CacheClearer;

/**
 * php spark app:cache-clear
 *
 * CodeIgniter's own `cache:clear` cannot be replaced (command discovery keeps the first command
 * with a name, D-00-43), so this one clears the Core's caches and CodeIgniter's cache and fires
 * `cache.cleared` for the caches modules keep elsewhere.
 */
final class AppCacheClear extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'app:cache-clear';
    protected $description = "Clears the Core's caches (permissions, menus, settings, widgets, assets, modules) and CodeIgniter's cache, and fires the cache.cleared event.";
    protected $usage       = 'app:cache-clear';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        if (! (new CacheClearer())->clear()) {
            CLI::error(lang('Install.cache.failed', [config('Cache')->handler]));

            return EXIT_ERROR;
        }

        CLI::write(lang('Install.cache.cleared', [config('Cache')->handler]), 'green');

        return EXIT_SUCCESS;
    }
}
