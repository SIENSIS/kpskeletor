<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

/**
 * The module registry (`modules` table): installed version and state of every module.
 */
final class ModuleModel extends Model
{
    protected $table         = 'modules';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['name', 'version', 'enabled', 'installed_at', 'updated_at'];
    protected $useTimestamps = false;

    /**
     * @return array<string, array{id: int, name: string, version: string, enabled: bool, installed_at: string|null, updated_at: string|null}>
     */
    public function allByName(): array
    {
        $rows = [];

        foreach ($this->orderBy('name')->findAll() as $row) {
            $rows[(string) $row['name']] = [
                'id'           => (int) $row['id'],
                'name'         => (string) $row['name'],
                'version'      => (string) $row['version'],
                'enabled'      => (bool) $row['enabled'],
                'installed_at' => $row['installed_at'] === null ? null : (string) $row['installed_at'],
                'updated_at'   => $row['updated_at'] === null ? null : (string) $row['updated_at'],
            ];
        }

        return $rows;
    }
}
