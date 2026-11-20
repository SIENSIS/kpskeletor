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
 * with their own values (`faker: false`: numbered demo values, the same on every PHP version —
 * Faker's seeded sequence differs between PHP 8.2 and 8.3).
 */
final class GeneratorContext
{
    /** @var array<string, ModuleManifest>|null */
    private ?array $manifests;

    /** @var \Closure(string): bool */
    private \Closure $tableExists;

    /** @var list<string>|null */
    private ?array $groups;

    /** @var array<string, string> group name => title (shown next to the name) */
    private array $titles = [];

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
        public readonly bool $faker = true,
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
     * The title of a group as the site shows it (Users › Groups: e.g. "Coordinador" for the
     * reserved group `editor`), or its name when it has none.
     */
    public function groupTitle(string $group): string
    {
        $this->groups();
        $title = trim($this->titles[$group] ?? '');

        return $title !== '' ? $title : $group;
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
                $all          = (new GroupRegistry())->all();
                $this->groups = array_values(array_map(static fn (array $g): string => (string) $g['name'], $all));
                $this->titles = array_map(static fn (array $g): string => (string) $g['title'], $all);
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
