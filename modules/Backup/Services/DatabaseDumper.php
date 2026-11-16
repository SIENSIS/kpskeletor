<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\MigrationRunner;
use Config\Database;
use Config\Migrations;
use Modules\Backup\Config\Backup as BackupConfig;

/**
 * The portable database dump of a backup (docs/backup-restore.md, D-27-03): every table of the
 * application (tables with the configured prefix), read with the Query Builder in chunks and
 * written as JSON lines, one file per table (`<table>.jsonl`: a first line with the columns, then
 * one array of values per row; strings that are not UTF-8 are stored as `{"b64": "…"}`). Names
 * are stored without the prefix, so a dump restores into another prefix, driver or server.
 *
 * Restoring never trusts the dump for the schema: the tables are dropped and recreated by
 * running, in their original order, the migrations listed in the dump's own `migrations` table
 * (the code of a migration never changes once released), then emptied and filled with the rows.
 * Later migrations of the installed code run afterwards with `modules:sync`.
 */
final class DatabaseDumper
{
    public const MIGRATIONS_TABLE = 'migrations';

    private readonly BackupConfig $config;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(private readonly ?BaseConnection $db = null, ?BackupConfig $config = null)
    {
        $this->config = $config ?? config(BackupConfig::class);
    }

    /**
     * Writes one `<table>.jsonl` per table into `$folder`.
     *
     * @return array<string, int> table (without prefix) => rows
     */
    public function dump(string $folder): array
    {
        $db     = $this->db();
        $counts = [];

        foreach ($this->tables() as $table) {
            $file = $folder . '/' . $table . '.jsonl';
            $out  = fopen($file, 'xb');

            if ($out === false) {
                throw new BackupException(lang('Backup.error.write'));
            }

            $fields  = $db->getFieldData($table);
            $columns = array_map(static fn (object $field): string => (string) $field->name, $fields);
            $keys    = array_values(array_map(static fn (object $field): string => (string) $field->name, array_filter($fields, static fn (object $field): bool => ! empty($field->primary_key))));
            $order   = $keys !== [] ? $keys : $columns;
            fwrite($out, json_encode(['columns' => $columns], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

            $rows   = 0;
            $offset = 0;

            do {
                $builder = $db->table($table)->select($columns);

                foreach ($order as $column) {
                    $builder->orderBy($column, 'ASC');
                }

                $chunk = $builder->limit($this->config->dumpChunk, $offset)->get()->getResultArray();

                foreach ($chunk as $row) {
                    $values = [];

                    foreach ($columns as $column) {
                        $values[] = self::encode($row[$column] ?? null);
                    }

                    fwrite($out, json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n");
                }

                $rows += count($chunk);
                $offset += $this->config->dumpChunk;
            } while (count($chunk) === $this->config->dumpChunk);

            fclose($out);
            $counts[$table] = $rows;
        }

        return $counts;
    }

    /**
     * The application's tables, without the prefix.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $db = $this->db();
        $db->resetDataCache();
        $prefix = $db->DBPrefix;
        $tables = [];

        foreach ($db->listTables() as $name) {
            $name = (string) $name;

            if ($prefix !== '' && ! str_starts_with($name, $prefix)) {
                continue;
            }

            $tables[] = substr($name, strlen($prefix));
        }

        sort($tables);

        return $tables;
    }

    /**
     * The migrations a dump needs, in order, with their files in the installed code.
     *
     * @return list<array{class: string, namespace: string, path: string}>
     *
     * @throws BackupException when a migration of the dump does not exist in this installation
     */
    public function plan(string $folder): array
    {
        $file = $folder . '/' . self::MIGRATIONS_TABLE . '.jsonl';

        if (! is_file($file)) {
            throw new BackupException(lang('Backup.error.noMigrations'));
        }

        $rows    = iterator_to_array($this->rows($file), false);
        $columns = $this->columns($file);
        $runner  = new MigrationRunner(config(Migrations::class), $this->db());
        $known   = [];
        $plan    = [];
        $missing = [];

        usort($rows, static fn (array $a, array $b): int => [(int) ($a['batch'] ?? 0), (int) ($a['id'] ?? 0)] <=> [(int) ($b['batch'] ?? 0), (int) ($b['id'] ?? 0)]);

        foreach ($rows as $row) {
            $namespace = (string) ($row['namespace'] ?? '');
            $class     = ltrim((string) ($row['class'] ?? ''), '\\');

            if (! isset($known[$namespace])) {
                $known[$namespace] = [];

                foreach ($runner->findNamespaceMigrations($namespace) as $migration) {
                    $known[$namespace][ltrim((string) $migration->class, '\\')] = (string) $migration->path;
                }
            }

            if (! isset($known[$namespace][$class])) {
                $missing[] = $class;

                continue;
            }

            $plan[] = ['class' => $class, 'namespace' => $namespace, 'path' => $known[$namespace][$class]];
        }

        if ($missing !== [] || ! in_array('class', $columns, true)) {
            throw new BackupException(lang('Backup.error.unknownMigrations', [implode(', ', array_slice($missing, 0, 5))]));
        }

        return $plan;
    }

    /**
     * Replaces the whole database with the dump in `$folder` (see the class comment).
     *
     * @param list<array{class: string, namespace: string, path: string}> $plan     From plan()
     * @param (callable(int, int): void)|null                             $progress Called with (tables done, tables)
     */
    public function restore(string $folder, array $plan, ?callable $progress = null): void
    {
        $db    = $this->db();
        $forge = Database::forge($db);
        $db->disableForeignKeyChecks();

        try {
            // 1. Drop every table of the application.
            foreach ($this->tables() as $table) {
                $forge->dropTable($table, true);
            }

            $db->resetDataCache();

            // 2. Recreate the schema of the backup: its migrations, in order.
            (new MigrationRunner(config(Migrations::class), $db))->ensureTable();

            foreach ($plan as $step) {
                require_once $step['path'];
                $class = $step['class'];

                if (! class_exists($class, false)) {
                    throw new BackupException(lang('Backup.error.unknownMigrations', [$class]));
                }

                $migration = new $class($forge);
                $migration->up();
            }

            $db->resetDataCache();
            $existing = $this->tables();
            $files    = glob($folder . '/*.jsonl') ?: [];
            $total    = count($files);

            // 3. Empty every table (migrations may insert rows), then load the dump. Foreign keys
            // are off again: altering a SQLite table inside a migration turns them back on.
            $db->disableForeignKeyChecks();

            foreach ($existing as $table) {
                $db->table($table)->emptyTable();
            }

            foreach ($files as $index => $file) {
                $table = basename($file, '.jsonl');

                if (in_array($table, $existing, true)) {
                    $this->load($table, $file);
                }

                if ($progress !== null) {
                    $progress($index + 1, $total);
                }
            }
        } finally {
            $db->enableForeignKeyChecks();
            $db->resetDataCache();
        }
    }

    /**
     * The rows of a dump file as associative arrays.
     *
     * @return \Generator<int, array<string, mixed>>
     */
    public function rows(string $file): \Generator
    {
        $in = fopen($file, 'rb');

        if ($in === false) {
            throw new BackupException(lang('Backup.error.damaged'));
        }

        try {
            $columns = $this->decodeColumns((string) fgets($in));

            while (($line = fgets($in)) !== false) {
                if (trim($line) === '') {
                    continue;
                }

                $values = json_decode($line, true);

                if (! is_array($values) || count($values) !== count($columns)) {
                    throw new BackupException(lang('Backup.error.damaged'));
                }

                yield array_combine($columns, array_map(self::decode(...), $values));
            }
        } finally {
            fclose($in);
        }
    }

    /**
     * @return list<string>
     */
    private function columns(string $file): array
    {
        $in = fopen($file, 'rb');

        if ($in === false) {
            throw new BackupException(lang('Backup.error.damaged'));
        }

        $line = (string) fgets($in);
        fclose($in);

        return $this->decodeColumns($line);
    }

    /**
     * @return list<string>
     */
    private function decodeColumns(string $line): array
    {
        $header = json_decode($line, true);

        if (! is_array($header) || ! is_array($header['columns'] ?? null) || $header['columns'] === []) {
            throw new BackupException(lang('Backup.error.damaged'));
        }

        $columns = [];

        foreach ($header['columns'] as $column) {
            if (! is_string($column) || preg_match('/^[A-Za-z0-9_]+$/', $column) !== 1) {
                throw new BackupException(lang('Backup.error.damaged'));
            }

            $columns[] = $column;
        }

        return $columns;
    }

    private function load(string $table, string $file): void
    {
        $db      = $this->db();
        $fields  = $db->getFieldNames($table);
        $batch   = [];

        foreach ($this->rows($file) as $row) {
            // Columns dropped by a later migration of the backup's own code cannot exist here.
            $batch[] = array_intersect_key($row, array_flip($fields));

            if (count($batch) >= $this->config->dumpChunk) {
                $db->table($table)->insertBatch($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $db->table($table)->insertBatch($batch);
        }
    }

    private static function encode(mixed $value): mixed
    {
        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            return ['b64' => base64_encode($value)];
        }

        return $value;
    }

    private static function decode(mixed $value): mixed
    {
        if (is_array($value)) {
            $decoded = isset($value['b64']) && is_string($value['b64']) ? base64_decode($value['b64'], true) : false;

            if ($decoded === false) {
                throw new BackupException(lang('Backup.error.damaged'));
            }

            return $decoded;
        }

        if ($value !== null && ! is_scalar($value)) {
            throw new BackupException(lang('Backup.error.damaged'));
        }

        return $value;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
