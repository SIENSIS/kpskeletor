<?php

declare(strict_types=1);

namespace Modules\Core\Config;

/**
 * Manifest of the Core module: the kernel every other module depends on.
 */
final class Module extends ModuleManifest
{
    public string $name    = 'Core';
    public string $version = Version::CORE;
    public string $icon    = 'bi bi-cpu';
}
