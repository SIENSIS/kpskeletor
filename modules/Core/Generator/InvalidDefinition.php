<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * A definition with errors: `errors()` gives them by path (`tables.0.columns.1.name` => message).
 */
final class InvalidDefinition extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode("\n", array_map(static fn (string $path, string $message): string => $path . ': ' . $message, array_keys($errors), $errors)));
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
