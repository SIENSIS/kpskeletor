<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\CodeIgniter;
use Modules\Core\Config\Version;
use Modules\Core\Models\ModuleModel;
use Modules\Core\Modules\ModuleDiscovery;
use Throwable;

/**
 * php spark about
 *
 * The application, its environment and its modules at a glance (for support requests). Never
 * prints secrets: keys are only reported as set or not set.
 */
final class About extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'about';
    protected $description = 'Shows the versions, the environment, the database, the caches and the modules of the application.';
    protected $usage       = 'about';

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $state = service('installstate');
        $lock  = $state->lockData();
        $set   = static fn (bool $value): string => lang($value ? 'Install.about.set' : 'Install.about.notSet');

        CLI::write(lang('Install.about.application'), 'green');
        CLI::table([
            [lang('Install.about.name'), $this->setting('Core.app.name')],
            [lang('Install.about.core'), Version::CORE],
            ['CodeIgniter', CodeIgniter::CI_VERSION],
            ['PHP', PHP_VERSION . ' (' . PHP_SAPI . ')'],
            [lang('Install.about.environment'), ENVIRONMENT],
            [lang('Install.about.baseURL'), (string) config('App')->baseURL],
            [lang('Install.about.installed'), is_array($lock) ? (string) ($lock['installed_at'] ?? '?') : lang('Install.about.notInstalled')],
            ['.env', $set($state->hasEnv())],
            ['encryption.key', $set((string) config('Encryption')->key !== '')],
            ['app.maskKey', $set(trim((string) config('App')->maskKey) !== '')],
            [lang('Install.about.timezone'), $this->setting('Core.timezone', config('App')->appTimezone)],
            [lang('Install.about.locale'), config('App')->defaultLocale . ' (' . implode(', ', config('App')->supportedLocales) . ')'],
            [lang('Install.about.cache'), config('Cache')->handler],
            [lang('Install.about.writable'), lang(is_writable(WRITEPATH) ? 'Core.list.yes' : 'Core.list.no')],
        ], [lang('Install.about.item'), lang('Install.about.value')]);

        CLI::write(lang('Install.about.database'), 'green');
        CLI::table($this->database(), [lang('Install.about.item'), lang('Install.about.value')]);

        CLI::write(lang('Install.about.modules'), 'green');
        CLI::table($this->modules(), [lang('Core.list.module'), lang('Core.list.version'), lang('Core.list.installed'), lang('Core.list.enabled')]);

        return EXIT_SUCCESS;
    }

    /**
     * A setting as text; the fallback before installation (no settings table).
     */
    private function setting(string $name, string $fallback = '-'): string
    {
        try {
            $value = setting($name);
        } catch (Throwable) {
            $value = null;
        }

        return is_scalar($value) && (string) $value !== '' ? (string) $value : $fallback;
    }

    /**
     * @return list<list<string>>
     */
    private function database(): array
    {
        try {
            $db = db_connect();
            $db->initialize();

            return [
                [lang('Install.about.driver'), $db->getPlatform()],
                [lang('Install.about.version'), $db->getVersion()],
                [lang('Install.about.dbName'), $db->getDatabase()],
                [lang('Install.about.tables'), (string) count($db->listTables())],
            ];
        } catch (Throwable $e) {
            return [[lang('Install.about.driver'), lang('Install.about.dbError', [$e->getMessage()])]];
        }
    }

    /**
     * @return list<list<string>>
     */
    private function modules(): array
    {
        try {
            $registry = db_connect()->tableExists('modules') ? model(ModuleModel::class, false)->allByName() : [];
        } catch (Throwable) {
            $registry = [];
        }

        $rows = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            $installed = $registry[$name] ?? null;
            $rows[]    = [
                $name,
                $module->manifest->version ?? '?',
                $installed['version'] ?? '-',
                $installed === null ? '-' : lang($installed['enabled'] ? 'Core.list.yes' : 'Core.list.no'),
            ];
        }

        return $rows;
    }
}
