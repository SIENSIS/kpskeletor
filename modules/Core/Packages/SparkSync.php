<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Modules\Core\Modules\SyncReport;

/**
 * Runs `php spark modules:sync` in a new PHP process after the code of a module changed: the
 * running process may already have loaded the previous version's classes (its manifest), which
 * PHP cannot unload. The output is passed on line by line; a non-zero exit is a failure.
 */
final class SparkSync
{
    /**
     * @param \Closure(string, string): void $say
     */
    public function __construct(private readonly \Closure $say, private readonly string $binary = '')
    {
    }

    /**
     * @throws PackageRefused with the command's output when it fails
     */
    public function __invoke(): SyncReport
    {
        if (! PhpLint::available()) {
            throw new PackageRefused(lang('Core.packages.lintUnavailable'));
        }

        $command = [$this->binary !== '' ? $this->binary : PhpLint::phpBinary(), ROOTPATH . 'spark', 'modules:sync'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']], $pipes, ROOTPATH);

        if (! is_resource($process)) {
            throw new PackageRefused(lang('Core.packages.lintUnavailable'));
        }

        fclose($pipes[0]);
        $output = [];

        while (($line = fgets($pipes[1])) !== false) {
            $line = rtrim($line, "\r\n");

            if ($line === '' || str_starts_with($line, 'CodeIgniter v')) {
                continue;
            }

            $output[] = $line;
            ($this->say)('  ' . $line, '');
        }

        fclose($pipes[1]);

        if (proc_close($process) !== 0) {
            throw new PackageRefused(implode("\n", array_slice($output, -20)));
        }

        return new SyncReport();
    }
}
