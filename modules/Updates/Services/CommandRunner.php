<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

/**
 * Runs an external program for `app:update` (git, composer, a new `php spark` process).
 */
interface CommandRunner
{
    /**
     * @param list<string> $command program and arguments (never a shell string)
     *
     * @return array{code: int, output: string}
     */
    public function run(array $command, string $cwd): array;
}
