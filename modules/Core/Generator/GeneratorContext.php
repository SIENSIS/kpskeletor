<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use Modules\Core\Config\ModuleManifest;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Modules\ModuleDiscovery;

/**
 * What the generator needs to know about the installation: the folder of the modules, the
 * installed modules' manifests (dependencies, existing resources, foreign-key targets), whether a
 * table exists in the database, and the groups that can receive default grants. Tests build one
 * with their own values.
 */
final class GeneratorContext
{
    /** @var array<string, ModuleManifest>|null */
    private ?array $manifests;

    /** @var \Closure(string): bool */
    private \Closure $tableExists;

    /** @var list<string>|null */
    private ?array $groups;

    /**
     * @param array<string, ModuleManifest>|null $manifests name => manifest (default: discovered)
     * @param (\Closure(string): bool)|null      $tableExists
     * @param list<string>|null                  $groups
     */
    public function __construct(
        public readonly string $root = ROOTPATH . 'modules',
        ?array $manifests = null,
        ?\Closure $tableExists = null,
        ?array $groups = null,
        public readonly string $projectRoot = ROOTPATH,
    ) {
        $this->manifests   = $manifests;
        $this->tableExists = $tableExists ?? static function (string $table): bool {
            try {
                return db_connect()->tableExists($table);
            } catch (\Throwable) {
                return false;
            }
        };
        $this->groups = $groups;
    }

    /**
     * @return array<string, ModuleManifest>
     */
    public function manifests(): array
    {
        if ($this->manifests === null) {
            $this->manifests = [];

            foreach ((new ModuleDiscovery([$this->root]))->discover() as $name => $module) {
                if ($module->manifest !== null) {
                    $this->manifests[$name] = $module->manifest;
                }
            }
        }

        return $this->manifests;
    }

    public function manifest(string $module): ?ModuleManifest
    {
        return $this->manifests()[$module] ?? null;
    }

    public function modulePath(string $module): string
    {
        return rtrim($this->root, '/\\') . '/' . $module;
    }

    public function tableExists(string $table): bool
    {
        return ($this->tableExists)($table);
    }

    /**
     * Column names of an installed table (empty when it does not exist).
     *
     * @return list<string>
     */
    public function fieldNames(string $table): array
    {
        try {
            return $this->tableExists($table) ? array_values(db_connect()->getFieldNames($table)) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        if ($this->groups === null) {
            try {
                $this->groups = array_values(array_map(static fn (array $g): string => (string) $g['name'], (new GroupRegistry())->all()));
            } catch (\Throwable) {
                $this->groups = [];
            }

            if ($this->groups === []) {
                $this->groups = ['superadmin', 'admin', 'editor', 'user'];
            }
        }

        return $this->groups;
    }
}
