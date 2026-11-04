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

    public function hasChanges(): bool
    {
        return $this->namespacesChanged
            || array_sum($this->migrations) > 0
            || $this->installed !== []
            || $this->upgraded !== []
            || $this->seeded !== [];
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

        return $lines;
    }
}
