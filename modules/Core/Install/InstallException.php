<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use RuntimeException;

/**
 * The installation cannot go on. `$errors` holds the invalid options (field => message) when
 * the input is the reason.
 */
final class InstallException extends RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
