<?php

declare(strict_types=1);

namespace Modules\Core\Modules;

/**
 * Thrown when manifests or dependencies are invalid: nothing has been changed.
 */
final class SyncFailedException extends \RuntimeException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(lang('Core.sync.failed') . "\n- " . implode("\n- ", $errors));
    }
}
