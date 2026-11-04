<?php

declare(strict_types=1);

namespace Modules\Core\Demo;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;
use InvalidArgumentException;

/**
 * The helper every `<Name>DemoSeeder` writes through: it inserts rows and records each one
 * (`demo_records`: module, table, key column, primary key) and every file it stores, so that
 * "remove demo data" deletes exactly those rows and files and nothing else.
 */
final class DemoData
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(
        public readonly string $module,
        private readonly ?BaseConnection $db = null,
    ) {
    }

    /**
     * Inserts a row and records it.
     *
     * @param array<string, mixed> $row
     *
     * @return int|string The primary key of the new row
     */
    public function insert(string $table, array $row, string $keyColumn = 'id'): int|string
    {
        if (! $this->db()->table($table)->insert($row)) {
            throw new InvalidArgumentException(lang('Core.demo.insertFailed', [$table]));
        }

        $id = $row[$keyColumn] ?? $this->db()->insertID();
        $this->record($table, $id, $keyColumn);

        return is_int($id) || is_string($id) ? $id : (string) $id;
    }

    /**
     * Records a row created some other way (e.g. through a model or a library).
     */
    public function record(string $table, int|string $id, string $keyColumn = 'id'): void
    {
        $this->db()->table('demo_records')->insert([
            'module'     => $this->module,
            'table_name' => $table,
            'key_column' => $keyColumn,
            'record_id'  => (string) $id,
            'created_at' => Time::now()->toDateTimeString(),
        ]);
    }

    /**
     * Records a file the seeder stored (path relative to WRITEPATH), removed with the demo data.
     */
    public function recordFile(string $path): void
    {
        $this->db()->table('demo_records')->insert([
            'module'     => $this->module,
            'file_path'  => ltrim($path, '/'),
            'created_at' => Time::now()->toDateTimeString(),
        ]);
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
