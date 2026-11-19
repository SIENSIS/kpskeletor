<?php

declare(strict_types=1);

namespace Modules\Core\Routing;

use RuntimeException;
use Throwable;

/**
 * `app:backend-path` failed at a step (`env`, `links` or `cache`); maintenance mode was left on.
 */
final class BackendPathChangeFailed extends RuntimeException
{
    public function __construct(public readonly string $step, public readonly string $old, public readonly string $new, Throwable $previous)
    {
        parent::__construct($previous->getMessage(), 0, $previous);
    }
}
