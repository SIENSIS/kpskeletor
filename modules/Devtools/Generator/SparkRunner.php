<?php

declare(strict_types=1);

namespace Modules\Devtools\Generator;

/**
 * Runs `php spark <command>` in a new process after the generator wrote a module (so it sees the
 * new module's namespace), answering confirmation prompts from `$input`. Never takes user text:
 * the arguments are built by the controller from a validated module name.
 */
class SparkRunner
{
    /**
     * @param list<string> $arguments
     *
     * @return array{status: int, output: string}
     */
    public function run(array $arguments, string $input = ''): array
    {
        $command = [PHP_BINARY, ROOTPATH . 'spark', ...$arguments, '--no-header'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOTPATH);

        if (! is_resource($process)) {
            return ['status' => 1, 'output' => 'proc_open'];
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'output' => trim($output)];
    }
}
