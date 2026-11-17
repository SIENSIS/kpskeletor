<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

/**
 * Looks for secrets in the files git tracks (`git ls-files`): private keys, access tokens of
 * GitHub, GitLab, AWS, Slack and Stripe, and `.env` keys that hold a secret (encryption key,
 * mask key, passwords) written with a value. A line that carries the marker
 * `security-check: ignore` is skipped (fixtures with fake keys), and so is an `.env` line whose
 * value is an obvious placeholder (`choose-a-password`, `<your key>`…). It is a safety net for an
 * installation; CI's secret scanning covers the history.
 */
final class SecretScanner
{
    public const IGNORE_MARKER = 'security-check: ignore';

    /** Name => pattern. */
    public const PATTERNS = [
        'private key'  => '/-----BEGIN (?:RSA |EC |DSA |OPENSSH |ENCRYPTED |PGP )?PRIVATE KEY(?: BLOCK)?-----/',
        'GitHub token' => '/\b(?:gh[pousr]_[A-Za-z0-9]{36,}|github_pat_[A-Za-z0-9_]{50,})\b/',
        'GitLab token' => '/\bglpat-[A-Za-z0-9_\-]{20,}\b/',
        'AWS key'      => '/\bAKIA[0-9A-Z]{16}\b/',
        'Slack token'  => '/\bxox[abprs]-[A-Za-z0-9-]{10,}\b/',
        'Stripe key'   => '/\b[sr]k_live_[A-Za-z0-9]{20,}\b/',
        '.env secret'  => '/^\s*(?:encryption\.key|app\.maskKey|backup\.password|database\.\w+\.password|email\.SMTPPass|updates\.token|BACKUP_PASSWORD|INSTALL_\w*PASSWORD)\s*=\s*["\']?[^\s"\'#]{8,}/m',
    ];

    /** Paths (prefixes) never scanned: third-party code. */
    public const EXCLUDED = ['vendor/', 'node_modules/', 'public/assets/vendor/'];

    private const MAX_BYTES = 1048576;

    public function __construct(private readonly string $root = ROOTPATH, private readonly int $maxFiles = 20000)
    {
    }

    /**
     * Tracked files, or null when this is not a git checkout (or git is missing).
     *
     * @return list<string>|null
     */
    public function trackedFiles(): ?array
    {
        if (! is_dir($this->root . '.git') && ! is_file($this->root . '.git')) {
            return null;
        }

        $process = @proc_open(['git', '-C', $this->root, 'ls-files', '-z'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            return null;
        }

        return array_values(array_filter(explode("\0", $output), static fn (string $file): bool => $file !== ''));
    }

    /**
     * Findings (`<file>:<line> (<kind>)`), never the secret itself; null when nothing could be
     * scanned.
     *
     * @param list<string>|null $files relative paths; default: the tracked files
     *
     * @return list<string>|null
     */
    public function scan(?array $files = null): ?array
    {
        $files ??= $this->trackedFiles();

        if ($files === null) {
            return null;
        }

        $findings = [];
        $count    = 0;

        foreach ($files as $file) {
            if ($this->excluded($file) || ++$count > $this->maxFiles) {
                continue;
            }

            $path = $this->root . $file;

            if (! is_file($path) || is_link($path) || (int) @filesize($path) > self::MAX_BYTES) {
                continue;
            }

            $content = (string) @file_get_contents($path);

            if ($content === '' || str_contains($content, "\0")) {
                continue;
            }

            foreach (self::PATTERNS as $kind => $pattern) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) < 1) {
                    continue;
                }

                foreach ($matches[0] as [$match, $offset]) {
                    $lineStart = strrpos(substr($content, 0, (int) $offset), "\n");
                    $lineEnd   = strpos($content, "\n", (int) $offset);
                    $line      = substr($content, $lineStart === false ? 0 : $lineStart + 1, ($lineEnd === false ? strlen($content) : $lineEnd) - ($lineStart === false ? 0 : $lineStart + 1));

                    if (str_contains($line, self::IGNORE_MARKER) || ($kind === '.env secret' && self::placeholder($line))) {
                        continue;
                    }

                    $findings[] = $file . ':' . (substr_count($content, "\n", 0, (int) $offset) + 1) . ' (' . $kind . ')';
                }
            }
        }

        return $findings;
    }

    /**
     * Whether the value of an `.env` line is an obvious placeholder (documentation, templates).
     */
    private static function placeholder(string $line): bool
    {
        $value = strtolower(trim((string) substr($line, (int) strpos($line, '=') + 1), " \t\"'"));

        return preg_match('/choose|change|your|example|placeholder|replace|secret|password|xxx|<|\{|\$|%/', $value) === 1;
    }

    private function excluded(string $file): bool
    {
        foreach (self::EXCLUDED as $prefix) {
            if (str_starts_with($file, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
