<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * `php -l` on the PHP files of an extracted package (syntax only; nothing is run). Each file is
 * checked by a separate `php -l` process with a time limit; the first error refuses the package.
 */
final class PhpLint
{
    public function __construct(private readonly string $binary = '', private readonly int $timeout = 20)
    {
    }

    public static function available(): bool
    {
        return function_exists('proc_open');
    }

    /**
     * @param list<string> $files absolute paths
     *
     * @throws PackageRefused
     */
    public function check(array $files, string $base): void
    {
        if (! self::available()) {
            throw new PackageRefused(lang('Core.packages.lintUnavailable'));
        }

        foreach ($files as $file) {
            $result = $this->run($file);

            if ($result !== null) {
                $relative = ltrim(substr($file, strlen(rtrim($base, '/'))), '/');

                throw new PackageRefused(lang('Core.packages.lintFailed', [$relative, $result]));
            }
        }
    }

    /**
     * @return string|null The error, or null when the syntax is valid
     */
    private function run(string $file): ?string
    {
        $binary  = $this->binary !== '' ? $this->binary : self::phpBinary();
        $command = [$binary, '-n', '-d', 'display_errors=stderr', '-l', $file];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return lang('Core.packages.lintUnavailable');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output   = '';
        $deadline = microtime(true) + $this->timeout;

        do {
            $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);

            if (! $status['running']) {
                break;
            }

            if (microtime(true) > $deadline) {
                proc_terminate($process);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                return lang('Core.packages.lintTimeout');
            }

            usleep(10_000);
        } while (true);

        $output .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = $status['exitcode'];
        proc_close($process);

        if ($code === 0) {
            return null;
        }

        // "PHP Parse error: syntax error, unexpected ... in /path/file.php on line 3"
        $line = trim((string) strtok(trim($output), "\n"));
        $line = str_replace($file, basename($file), $line);

        return mb_substr($line !== '' ? $line : 'exit ' . $code, 0, 300);
    }

    public static function phpBinary(): string
    {
        $binary = PHP_BINARY;

        // Under PHP-FPM, PHP_BINARY is the FPM binary: use `php` from the PATH instead.
        if (str_contains(basename($binary), 'fpm') || str_contains(basename($binary), 'cgi')) {
            return 'php';
        }

        return $binary;
    }
}
