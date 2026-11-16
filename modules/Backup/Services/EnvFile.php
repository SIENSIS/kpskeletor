<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

/**
 * The `.env` of a restore (D-00-45): the current file is kept (database credentials and base URL
 * of this server) and only the secrets of the backup are merged into it after a confirmation;
 * `--with-env` (console only) replaces it whole. The back office never writes the project root:
 * it shows the merged content to copy.
 */
final class EnvFile
{
    /** The secrets a restore merges (a backup's encrypted values and masked ids need them). */
    public const SECRETS = ['encryption.key', 'app.maskKey', 'backup.password'];

    public function __construct(private readonly ?string $path = null)
    {
    }

    public function path(): string
    {
        return $this->path ?? ROOTPATH . '.env';
    }

    public function current(): ?string
    {
        $content = is_file($this->path()) ? @file_get_contents($this->path()) : false;

        return $content === false ? null : $content;
    }

    /**
     * The lines that set each secret (`key = value`, the last one wins like the loader).
     *
     * @return array<string, string> key => whole line
     */
    public function secretLines(string $content): array
    {
        $lines = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            foreach (self::SECRETS as $key) {
                if (preg_match('/^\s*(?:export\s+)?' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
                    $lines[$key] = rtrim($line);
                }
            }
        }

        return $lines;
    }

    /**
     * The secrets of the backup that differ from the current file (keys only).
     *
     * @return list<string>
     */
    public function differences(string $current, string $backup): array
    {
        $mine   = $this->secretLines($current);
        $theirs = $this->secretLines($backup);
        $diff   = [];

        foreach (self::SECRETS as $key) {
            if (isset($theirs[$key]) && $this->value($theirs[$key]) !== $this->value($mine[$key] ?? '')) {
                $diff[] = $key;
            }
        }

        return $diff;
    }

    /**
     * The current content with the backup's secret lines (replaced in place, or appended).
     */
    public function merge(string $current, string $backup): string
    {
        $theirs = $this->secretLines($backup);
        $done   = [];
        $lines  = preg_split('/\R/', $current) ?: [];

        foreach ($lines as $i => $line) {
            foreach ($theirs as $key => $replacement) {
                if (preg_match('/^\s*(?:export\s+)?' . preg_quote($key, '/') . '\s*=/', $line) === 1) {
                    $lines[$i]  = $replacement;
                    $done[$key] = true;
                }
            }
        }

        $content = rtrim(implode("\n", $lines), "\n");

        foreach ($theirs as $key => $replacement) {
            if (! isset($done[$key])) {
                $content .= "\n" . $replacement;
            }
        }

        return $content . "\n";
    }

    /**
     * Writes the file (console only), keeping its permissions (0640 for a new one).
     */
    public function write(string $content): void
    {
        $path  = $this->path();
        $perms = is_file($path) ? (fileperms($path) & 0777) : 0640;
        $tmp   = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';

        if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
            throw new BackupException(lang('Backup.error.envWrite'));
        }

        @chmod($tmp, $perms);

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            throw new BackupException(lang('Backup.error.envWrite'));
        }
    }

    private function value(string $line): string
    {
        $value = trim((string) substr($line, (int) strpos($line, '=') + 1));

        return trim($value, "\"'");
    }
}
