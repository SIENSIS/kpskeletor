<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use CodeIgniter\CLI\CLI;
use Modules\Core\Commands\ModulesSync;

/**
 * The console side of InstallPrompt (`php spark app:install`).
 */
final class CliPrompt implements InstallPrompt
{
    /**
     * @param array<int|string, string|null> $params
     */
    public function __construct(private readonly array $params, private readonly bool $interactive)
    {
    }

    public function interactive(): bool
    {
        return $this->interactive;
    }

    public function option(string $name): ?string
    {
        $value = ModulesSync::option($this->params, $name);

        return $value === null ? null : ($value === true ? '' : $value);
    }

    public function ask(string $question, array $options = [], ?string $default = null): string
    {
        if (! $this->interactive) {
            return $default ?? ($options[0] ?? '');
        }

        if ($options !== []) {
            return (string) CLI::prompt($question, $options);
        }

        return (string) CLI::prompt($question, $default);
    }

    /**
     * Asks without echoing the answer (passwords), when the terminal allows it.
     */
    public function secret(string $question): string
    {
        if (! $this->interactive) {
            return '';
        }

        CLI::print($question . ': ');
        $tty = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);

        if ($tty) {
            shell_exec('stty -echo');
        }

        $answer = fgets(STDIN);

        if ($tty) {
            shell_exec('stty echo');
        }

        CLI::newLine();

        return rtrim((string) $answer, "\r\n");
    }

    public function write(string $line, ?string $color = null): void
    {
        CLI::write($line, $color);
    }
}
