<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * Builds and writes the `.env` file from the `env` template of the project root.
 *
 * Each value replaces the template's line for its key (commented or not); keys the template
 * does not have are appended in an "Installer" section. Values are written in single quotes
 * with `\` and `'` escaped, which CodeIgniter's DotEnv reads back unchanged. A value with a
 * line break or a `${` (DotEnv would expand it as a variable) is refused.
 */
final class EnvWriter
{
    public function __construct(private readonly ?string $templatePath = null)
    {
    }

    /**
     * Whether a value can be written to `.env` unchanged.
     */
    public static function acceptable(string $value): bool
    {
        return ! str_contains($value, "\n") && ! str_contains($value, "\r") && ! str_contains($value, "\0") && ! str_contains($value, '${');
    }

    /**
     * The value as written after `key = `.
     */
    public static function quote(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        if (preg_match('/^[A-Za-z0-9_.:\/@+-]+$/', $value) === 1) {
            return $value;
        }

        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    /**
     * The `.env` content for these values.
     *
     * @param array<string, string> $values key => value (e.g. `database.default.hostname`)
     */
    public function render(array $values, string $header = ''): string
    {
        $template = $this->template();
        $missing  = [];

        foreach ($values as $key => $value) {
            if (! self::acceptable($value)) {
                throw new InstallException(lang('Install.error.envValue', [$key]));
            }

            $line    = $key . ' = ' . self::quote($value);
            $pattern = '/^[ \t]*#?[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*$/m';

            if (preg_match($pattern, $template) === 1) {
                $template = (string) preg_replace_callback($pattern, static fn (): string => $line, $template, 1);
            } else {
                $missing[] = $line;
            }
        }

        if ($missing !== []) {
            $template = rtrim($template) . "\n\n#--------------------------------------------------------------------\n"
                . "# INSTALLER\n#--------------------------------------------------------------------\n\n"
                . implode("\n", $missing) . "\n";
        }

        $header = $header === '' ? '' : '# ' . $header . "\n# Keep this file secret: it holds the keys and the database password.\n\n";

        return $header . rtrim($template) . "\n";
    }

    /**
     * Writes the content atomically (temporary file + rename) readable by the owner and the group (0640: the web server is often in the group).
     */
    public function write(string $path, string $content): void
    {
        $temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($temp, $content, LOCK_EX) === false) {
            throw new InstallException(lang('Install.error.envWrite', [$path]));
        }

        @chmod($temp, 0640);

        if (! @rename($temp, $path)) {
            @unlink($temp);

            throw new InstallException(lang('Install.error.envWrite', [$path]));
        }
    }

    /**
     * Adds lines for the given keys to an existing `.env` when they are missing or empty
     * (`php spark app:install` with a developer's `.env`). Returns the keys it added.
     *
     * @param array<string, string> $values
     *
     * @return list<string>
     */
    public function complete(string $path, array $values): array
    {
        $content = (string) file_get_contents($path);
        $added   = [];

        foreach ($values as $key => $value) {
            $pattern = '/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=[ \t]*(\S.*)?$/m';

            if (preg_match($pattern, $content, $match) === 1 && ! in_array(trim($match[1] ?? ''), ['', "''", '""'], true)) {
                continue;
            }

            $line = $key . ' = ' . self::quote($value);

            $content = preg_match($pattern, $content) === 1
                ? (string) preg_replace_callback($pattern, static fn (): string => $line, $content, 1)
                : rtrim($content) . "\n" . $line . "\n";
            $added[] = $key;
        }

        if ($added !== []) {
            $this->write($path, $content);
        }

        return $added;
    }

    private function template(): string
    {
        $path = $this->templatePath ?? ROOTPATH . 'env';

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
