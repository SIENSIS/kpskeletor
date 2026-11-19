<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use Modules\Core\Config\ModuleManifest;

/**
 * The rule of development-only modules (manifest `$developmentOnly`, D-41-01): such a module may
 * be installed and enabled only when the application runs in the `development` environment
 * (`CI_ENVIRONMENT`, the `ENVIRONMENT` constant). Used by `modules:sync`, `modules:enable`,
 * `modules:install`, the Modules screen and `security:check`; the environment is a constructor
 * argument so tests can play production or development.
 */
final class DevelopmentOnly
{
    public function __construct(private readonly string $environment = ENVIRONMENT)
    {
    }

    /** Whether development-only modules may be installed and enabled here. */
    public function allowed(): bool
    {
        return $this->environment === 'development';
    }

    /** Whether this manifest's module may not be installed or enabled here. */
    public function refuses(ModuleManifest $manifest): bool
    {
        return $manifest->developmentOnly && ! $this->allowed();
    }
}
