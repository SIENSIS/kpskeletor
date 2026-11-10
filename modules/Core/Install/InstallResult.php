<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * What an installation did, for the console output and the web installer's last page.
 */
final class InstallResult
{
    /** The `.env` content (to copy by hand when `$envWritten` is false). */
    public string $env = '';

    public bool $envWritten = false;

    /**
     * Keys added to an existing `.env` (console with a developer's `.env`).
     *
     * @var list<string>
     */
    public array $envCompleted = [];

    public int $superadminId = 0;

    /** @var list<string> */
    public array $syncLines = [];

    /** @var list<string> */
    public array $demoLoaded = [];

    /** Why the demo data could not be loaded ('' when it was, or was not asked for). */
    public string $demoError = '';

    public string $cronLine = '';

    public string $lockPath = '';
}
