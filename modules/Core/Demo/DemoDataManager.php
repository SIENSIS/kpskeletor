<?php

declare(strict_types=1);

namespace Modules\Core\Demo;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Core\Models\ModuleModel;

/**
 * Loads, removes and reloads the demo data of a module (`modules:demo`, `modules:sync --demo`,
 * later the installer and the Modules screen).
 *
 * - Loading runs `Modules\<Name>\Database\Seeds\<Name>DemoSeeder` (a DemoSeeder) in a
 *   transaction; it is idempotent: a module whose demo data is loaded is skipped.
 * - Removing deletes exactly the recorded rows (newest first, so children go before parents) and
 *   files; a row that other data now depends on is kept and reported.
 * - With CI_ENVIRONMENT=production both are refused unless `Packages.allowDemoInProduction`.
 */
final class DemoDataManager
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly string $environment = ENVIRONMENT,
        private readonly ?ModuleModel $registry = null,
    ) {
    }

    /**
     * @throws DemoDataException in production unless the setting allows it
     */
    public function assertAllowed(): void
    {
        if ($this->environment === 'production' && setting('Packages.allowDemoInProduction') !== true) {
            throw new DemoDataException(lang('Core.demo.refusedProduction'));
        }
    }

    public static function seederClass(string $module): string
    {
        return "Modules\\{$module}\\Database\\Seeds\\{$module}DemoSeeder";
    }

    public function hasSeeder(string $module): bool
    {
        $class = self::seederClass($module);

        return class_exists($class) && is_subclass_of($class, DemoSeeder::class);
    }

    public function isLoaded(string $module): bool
    {
        return $this->db()->table('demo_records')->where('module', $module)->countAllResults() > 0;
    }

    /**
     * Modules whose demo data is loaded.
     *
     * @return list<string>
     */
    public function loadedModules(): array
    {
        $rows = $this->db()->table('demo_records')->select('module')->distinct()->orderBy('module')->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['module'], $rows);
    }

    /**
     * Enabled modules that ship a demo seeder.
     *
     * @return list<string>
     */
    public function modulesWithDemo(): array
    {
        $modules = [];

        foreach ($this->registry()->allByName() as $name => $row) {
            if ($row['enabled'] && $this->hasSeeder($name)) {
                $modules[] = $name;
            }
        }

        return $modules;
    }

    /**
     * @return bool False when the demo data of the module was already loaded
     *
     * @throws DemoDataException
     */
    public function load(string $module): bool
    {
        $this->assertAllowed();
        $this->assertEnabled($module);

        if (! $this->hasSeeder($module)) {
            throw new DemoDataException(lang('Core.demo.noSeeder', [$module, self::seederClass($module)]));
        }

        if ($this->isLoaded($module)) {
            return false;
        }

        $class  = self::seederClass($module);
        $db     = $this->db();
        $seeder = new $class(config('Database'), $db);
        \assert($seeder instanceof DemoSeeder);

        // A failed transaction earlier on this connection leaves transStatus false, which would
        // make transComplete() roll back silently.
        $db->resetTransStatus()->transException(true)->transStart();

        try {
            $seeder->load(new DemoData($module, $db));

            if (! $db->transComplete()) {
                throw new \RuntimeException(lang('Core.demo.notCommitted'));
            }
        } catch (\Throwable $e) {
            $db->transRollback();

            throw new DemoDataException(lang('Core.demo.loadFailed', [$module, $e->getMessage()]), 0, $e);
        } finally {
            $db->transException(false);
        }

        activity_log('Core', 'demo_loaded', ['resource' => 'modules', 'summary' => $module]);

        return true;
    }

    /**
     * @throws DemoDataException
     */
    public function remove(string $module): DemoRemoval
    {
        $this->assertAllowed();

        $db      = $this->db();
        $removal = new DemoRemoval();
        $records = $db->table('demo_records')->where('module', $module)->orderBy('id', 'DESC')->get()->getResultArray();

        foreach ($records as $record) {
            if ($record['file_path'] !== null) {
                if ($this->deleteFile((string) $record['file_path'])) {
                    $removal->files++;
                }
            } elseif (! $this->deleteRow((string) $record['table_name'], (string) $record['key_column'], (string) $record['record_id'], $removal)) {
                continue; // kept: the record stays so the row is still known as demo data
            }

            $db->table('demo_records')->where('id', $record['id'])->delete();
        }

        activity_log('Core', 'demo_removed', ['resource' => 'modules', 'summary' => $module]);

        return $removal;
    }

    /**
     * Removes and loads the demo data again. If some rows had to be kept, nothing is loaded.
     */
    public function reload(string $module): DemoRemoval
    {
        $removal = $this->remove($module);

        if ($removal->kept === []) {
            $this->load($module);
        }

        return $removal;
    }

    /**
     * @return bool False when the row was kept because other data depends on it
     */
    private function deleteRow(string $table, string $keyColumn, string $id, DemoRemoval $removal): bool
    {
        $db = $this->db();

        if (! $db->tableExists($db->prefixTable($table), false)) {
            return true;
        }

        try {
            $db->table($table)->where($keyColumn, $id)->delete();
        } catch (\Throwable) {
            $removal->kept[] = "{$table}#{$id}";

            return false;
        }

        if ($db->affectedRows() > 0) {
            $removal->rows++;
        }

        return true;
    }

    /**
     * Deletes a recorded file, only inside WRITEPATH.
     */
    private function deleteFile(string $path): bool
    {
        $file = realpath(WRITEPATH . $path);
        $root = realpath(WRITEPATH);

        if ($file === false || $root === false || ! str_starts_with($file, $root . DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return false;
        }

        return unlink($file);
    }

    private function assertEnabled(string $module): void
    {
        $row = $this->registry()->allByName()[$module] ?? null;

        if ($row === null) {
            throw new DemoDataException(lang('Core.toggle.notInstalled', [$module]));
        }

        if (! $row['enabled']) {
            throw new DemoDataException(lang('Core.demo.disabled', [$module]));
        }
    }

    private function registry(): ModuleModel
    {
        return $this->registry ?? model(ModuleModel::class, false);
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
