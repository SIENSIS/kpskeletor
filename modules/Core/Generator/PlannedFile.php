<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * A file of a plan: its path inside the modules folder (`Library/Config/Module.php`), its full
 * new content, and its content on disk when the plan was made (null for a new file).
 */
final class PlannedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $content,
        public readonly ?string $original = null,
    ) {
    }

    /**
     * `new`, `changed` or `unchanged`.
     */
    public function status(): string
    {
        return match (true) {
            $this->original === null              => 'new',
            $this->original === $this->content    => 'unchanged',
            default                               => 'changed',
        };
    }

    /** Hash of the content on disk when the plan was made (null: the file did not exist). */
    public function originalHash(): ?string
    {
        return $this->original === null ? null : hash('sha256', $this->original);
    }

    public function withContent(string $content): self
    {
        return new self($this->path, $content, $this->original);
    }

    public function isPhp(): bool
    {
        return str_ends_with($this->path, '.php');
    }
}
