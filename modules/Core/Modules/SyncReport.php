<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

/**
 * What `modules:sync` changed. A second run on an unchanged installation reports nothing.
 */
final class SyncReport
{
    public bool $namespacesChanged = false;

    /** @var array<string, int> namespace => migrations run */
    public array $migrations = [];

    /** @var array<string, string> module => version, registered on first install */
    public array $installed = [];

    /** @var array<string, array{0: string, 1: string}> module => [from, to] */
    public array $upgraded = [];

    /** @var list<string> modules whose seeder ran */
    public array $seeded = [];

    /** @var list<string> registered modules whose folder no longer exists */
    public array $missing = [];

    /** @var list<string> module names in dependency order */
    public array $order = [];

    /** @var list<string> permissions added to the catalogue */
    public array $permissionsAdded = [];

    /** @var list<string> permissions whose module, resource, operation or description changed */
    public array $permissionsUpdated = [];

    /** @var list<string> permissions no manifest generates any more (kept until --prune) */
    public array $stalePermissions = [];

    /** @var list<string> permissions removed by --prune */
    public array $pruned = [];

    /** @var array<string, int> module => grants added on first install */
    public array $grantsApplied = [];

    public bool $declarationsChanged = false;

    /** @var list<string> modules whose demo data was loaded */
    public array $demoLoaded = [];

    public function hasChanges(): bool
    {
        return $this->namespacesChanged
            || array_sum($this->migrations) > 0
            || $this->installed !== []
            || $this->upgraded !== []
            || $this->seeded !== []
            || $this->permissionsAdded !== []
            || $this->permissionsUpdated !== []
            || $this->pruned !== []
            || $this->grantsApplied !== []
            || $this->declarationsChanged;
    }

    /**
     * Human-readable lines (translated) describing the changes.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        $lines = [];

        if ($this->namespacesChanged) {
            $lines[] = lang('Core.sync.namespaces');
        }

        foreach ($this->migrations as $namespace => $count) {
            if ($count > 0) {
                $lines[] = lang('Core.sync.migrated', [$namespace, $count]);
            }
        }

        foreach ($this->seeded as $module) {
            $lines[] = lang('Core.sync.seeded', [$module]);
        }

        foreach ($this->installed as $module => $version) {
            $lines[] = lang('Core.sync.installed', [$module, $version]);
        }

        foreach ($this->upgraded as $module => [$from, $to]) {
            $lines[] = lang('Core.sync.upgraded', [$module, $from, $to]);
        }

        foreach ($this->missing as $module) {
            $lines[] = lang('Core.sync.missing', [$module]);
        }

        if ($this->permissionsAdded !== []) {
            $lines[] = lang('Core.sync.permissionsAdded', [count($this->permissionsAdded)]);
        }

        if ($this->permissionsUpdated !== []) {
            $lines[] = lang('Core.sync.permissionsUpdated', [count($this->permissionsUpdated)]);
        }

        foreach ($this->grantsApplied as $module => $count) {
            $lines[] = lang('Core.sync.grantsApplied', [$module, $count]);
        }

        if ($this->declarationsChanged) {
            $lines[] = lang('Core.sync.declarations');
        }

        if ($this->pruned !== []) {
            $lines[] = lang('Core.sync.pruned', [implode(', ', $this->pruned)]);
        }

        if ($this->stalePermissions !== []) {
            $lines[] = lang('Core.sync.stale', [implode(', ', $this->stalePermissions)]);
        }

        foreach ($this->demoLoaded as $module) {
            $lines[] = lang('Core.sync.demoLoaded', [$module]);
        }

        return $lines;
    }
}
