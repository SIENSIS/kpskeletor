<?php

declare(strict_types=1);

namespace Modules\Core\Routing;

/**
 * Passed with the Core event `backendPathCheck` when a back-office prefix is validated: a module
 * that owns top-level paths outside its routes (Pages: top-level page slugs) refuses the
 * candidate with `refuse()`.
 */
final class BackendPathCheck
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(public readonly string $candidate)
    {
    }

    public function refuse(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
