<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use RuntimeException;

/**
 * A posted section that does not match its type's schema: the field's path (`title`,
 * `items.2.link`, `options.anchor`) and a message for people.
 */
final class SectionValidationException extends RuntimeException
{
    public function __construct(public readonly string $path, string $message)
    {
        parent::__construct($message);
    }

    public static function at(string $path, string $message): self
    {
        return new self($path, $message);
    }
}
