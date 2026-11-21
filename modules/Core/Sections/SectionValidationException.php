<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use RuntimeException;

/**
 * A posted section that does not match its type's schema: the field's path (`title`,
 * `items.2.link`, `options.anchor`), a message for people and, optionally, a link that helps to
 * fix it (e.g. the setting an administrator must change; shown only when given).
 */
final class SectionValidationException extends RuntimeException
{
    /**
     * @param array{url: string, label: string}|null $link
     */
    public function __construct(public readonly string $path, string $message, public readonly ?array $link = null)
    {
        parent::__construct($message);
    }

    /**
     * @param array{url: string, label: string}|null $link
     */
    public static function at(string $path, string $message, ?array $link = null): self
    {
        return new self($path, $message, $link);
    }

    /**
     * The error as the form views take it.
     *
     * @return array{path: string, message: string, link?: array{url: string, label: string}}
     */
    public function toArray(): array
    {
        return ['path' => $this->path, 'message' => $this->getMessage()] + ($this->link !== null ? ['link' => $this->link] : []);
    }
}
