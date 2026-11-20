<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * Runs php-cs-fixer (the project's rules, `.php-cs-fixer.dist.php`) on the PHP files of a plan,
 * in a temporary folder, so the preview shows exactly what will be written and the generated code
 * passes `php-cs-fixer fix --dry-run`. Skipped (files unchanged) when php-cs-fixer is not
 * installed (a `--no-dev` installation).
 */
final class CodeFormatter
{
    public function __construct(private readonly string $projectRoot = ROOTPATH)
    {
    }

    public function available(): bool
    {
        return is_file($this->binary()) && is_file($this->projectRoot . '.php-cs-fixer.dist.php');
    }

    /**
     * @param array<string, string> $files path => content
     *
     * @return array<string, string> path => formatted content
     */
    public function format(array $files): array
    {
        $php = array_filter($files, static fn (string $content, string $path): bool => str_ends_with($path, '.php'), ARRAY_FILTER_USE_BOTH);

        if ($php === [] || ! $this->available()) {
            return $files;
        }

        $dir = rtrim(sys_get_temp_dir(), '/\\') . '/kpgen-' . bin2hex(random_bytes(6));
        $map = [];
        $i   = 0;

        try {
            foreach ($php as $path => $content) {
                // Flat, numbered names: no folder may be called Views (excluded by the config).
                $name       = sprintf('%04d.php', $i++);
                $map[$name] = $path;

                if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                    return $files;
                }

                file_put_contents($dir . '/' . $name, $content);
            }

            $command = implode(' ', array_map(escapeshellarg(...), [
                PHP_BINARY, $this->binary(), 'fix', '--config=' . $this->projectRoot . '.php-cs-fixer.dist.php',
                '--using-cache=no', '--sequential', '--quiet', '--path-mode=override', $dir,
            ]));
            exec($command . ' 2>&1', $output, $status);

            foreach ($map as $name => $path) {
                $content = file_get_contents($dir . '/' . $name);

                if (is_string($content)) {
                    $files[$path] = $content;
                }
            }
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($dir);
        }

        return $files;
    }

    private function binary(): string
    {
        return $this->projectRoot . 'vendor/bin/php-cs-fixer';
    }
}
