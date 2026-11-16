<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * The latest available versions of the modules, for the Modules screen (event `moduleVersions`):
 * the Updates module's listener fills it from its last check. Without a listener the screen
 * shows only the installed versions.
 */
final class ModuleVersions
{
    public const EVENT = 'moduleVersions';

    /** @var array<string, string> */
    private array $latest = [];

    public function set(string $module, string $version): void
    {
        $this->latest[$module] = $version;
    }

    public function latest(string $module): ?string
    {
        return $this->latest[$module] ?? null;
    }
}
