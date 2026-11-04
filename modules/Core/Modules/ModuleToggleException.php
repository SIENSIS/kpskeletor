<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

use RuntimeException;

/**
 * A module cannot be enabled or disabled; the message is translated.
 */
final class ModuleToggleException extends RuntimeException
{
    /**
     * @param list<string> $modules The modules that block the change (dependants or disabled dependencies)
     */
    public function __construct(string $message, public readonly array $modules = [], public readonly bool $missingDependencies = false)
    {
        parent::__construct($message);
    }
}
