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
     * One-time secrets created by the seeder (label => value), shown once to whoever loaded the
     * demo data and never stored or logged.
     *
     * @var array<string, string>
     */
    private array $secrets = [];

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
     * A random password for a demo account (never a fixed password): 4 groups of 5 characters
     * from an alphabet without look-alikes, about 99 bits. Register it with secret() so it is
     * shown once.
     */
    public static function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $groups   = [];

        for ($g = 0; $g < 4; $g++) {
            $group = '';

            for ($i = 0; $i < 5; $i++) {
                $group .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $groups[] = $group;
        }

        return implode('-', $groups);
    }

    /**
     * Registers a secret (e.g. a demo user's password) to show once after loading.
     */
    public function secret(string $label, string $value): void
    {
        $this->secrets[$label] = $value;
    }

    /**
     * @return array<string, string> label => value
     */
    public function secrets(): array
    {
        return $this->secrets;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
