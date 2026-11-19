<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Core\Install\InstallProgress;

/**
 * A listener of the installer's steps that records them (tests).
 */
final class RecordingInstallProgress implements InstallProgress
{
    /** @var list<array{string, int, int, array<string, scalar>}> */
    public array $steps = [];

    /** @var list<string> */
    public array $logs = [];

    /** @var list<array{string, string}> */
    public array $failures = [];

    public function step(string $code, int $done = 0, int $total = 0, array $params = []): void
    {
        $this->steps[] = [$code, $done, $total, $params];
    }

    public function log(string $key, array $params = []): void
    {
        $this->logs[] = $key;
    }

    public function failed(string $code, string $message): void
    {
        $this->failures[] = [$code, $message];
    }
}
