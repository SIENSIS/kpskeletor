<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

/**
 * CommandRunner with `proc_open()` and an argument list (no shell, so nothing is interpolated).
 * Standard error (through a temporary file) is appended to the output. Console only.
 */
final class ProcessRunner implements CommandRunner
{
    public function run(array $command, string $cwd): array
    {
        $errors  = (string) tempnam(sys_get_temp_dir(), 'kpupd');
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes, $cwd);

        if (! is_resource($process)) {
            @unlink($errors);

            return ['code' => 127, 'output' => 'Could not start ' . $command[0]];
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code   = proc_close($process);
        $output .= (string) @file_get_contents($errors);
        @unlink($errors);

        return ['code' => $code, 'output' => $output];
    }
}
