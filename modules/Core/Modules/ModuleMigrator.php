<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use CodeIgniter\Database\MigrationRunner;
use Config\Database;
use Config\Migrations;

/**
 * Runs and rolls back the migrations of one namespace (a module or a third-party package).
 */
final class ModuleMigrator
{
    /** Third-party namespaces migrated before the modules, as they ship (never rolled back). */
    public const PACKAGE_NAMESPACES = ['CodeIgniter\Shield', 'CodeIgniter\Settings', 'Kpapy'];

    private function runner(): MigrationRunner
    {
        $runner = new MigrationRunner(config(Migrations::class));
        $runner->setSilent(true);
        $runner->setNamespace(null); // the runner defaults to APP_NAMESPACE

        return $runner;
    }

    /**
     * Migration files of a namespace that have not run yet, in version order.
     *
     * @return list<string> Migration names (e.g. "2026-11-04-000001_CreateModules")
     */
    public function pending(string $namespace): array
    {
        $runner  = $this->runner();
        $applied = [];

        $runner->ensureTable();

        foreach ($runner->getHistory($this->group()) as $row) {
            if ($row->namespace === $namespace) {
                $applied[$row->version . '_' . $row->class] = true;
            }
        }

        $pending = [];

        foreach ($this->sorted($runner->findNamespaceMigrations($namespace)) as $migration) {
            if (! isset($applied[$migration->version . '_' . $migration->class])) {
                $pending[] = basename($migration->path, '.php');
            }
        }

        return $pending;
    }

    /**
     * Runs the pending migrations of a namespace.
     *
     * @return int Number of migrations run
     */
    public function migrate(string $namespace): int
    {
        $count = count($this->pending($namespace));

        if ($count === 0) {
            return 0;
        }

        $runner = $this->runner();
        $runner->setNamespace($namespace);

        if (! $runner->latest()) {
            throw new \RuntimeException(lang('Core.sync.migrationFailed', [$namespace, implode(' ', $runner->getCliMessages())]));
        }

        return $count;
    }

    /**
     * Rolls back every applied migration of a namespace, newest first.
     *
     * @return int Number of migrations rolled back
     */
    public function rollback(string $namespace): int
    {
        if (in_array($namespace, self::PACKAGE_NAMESPACES, true)) {
            throw new \LogicException(lang('Core.sync.packageRollback', [$namespace]));
        }

        $runner  = $this->runner();
        $applied = [];

        $runner->ensureTable();

        foreach ($runner->getHistory($this->group()) as $row) {
            if ($row->namespace === $namespace) {
                $applied[$row->version . '_' . $row->class] = true;
            }
        }

        $count = 0;

        foreach (array_reverse($this->sorted($runner->findNamespaceMigrations($namespace))) as $migration) {
            if (isset($applied[$migration->version . '_' . $migration->class])) {
                $this->runner()->force($migration->path, $namespace);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param list<object> $migrations
     *
     * @return list<object{version: string, class: string, path: string}>
     */
    private function sorted(array $migrations): array
    {
        /** @var list<object{version: string, class: string, path: string}> $migrations */
        usort($migrations, static fn (object $a, object $b): int => strcmp($a->version, $b->version));

        return $migrations;
    }

    private function group(): string
    {
        return config(Database::class)->defaultGroup;
    }
}
