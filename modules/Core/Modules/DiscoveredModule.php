<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Modules\Core\Config\ModuleManifest;

/**
 * A module folder found on disk, with its manifest when it could be loaded.
 */
final class DiscoveredModule
{
    /**
     * @param list<string> $errors Problems found while loading or validating the manifest
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $namespace,
        public readonly ?ModuleManifest $manifest,
        public array $errors = [],
    ) {
    }

    public function isValid(): bool
    {
        return $this->manifest !== null && $this->errors === [];
    }
}
