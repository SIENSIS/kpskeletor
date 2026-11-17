<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use Closure;
use Modules\Core\Config\ProductionCheck;

/**
 * The last `composer audit` result. Composer has no offline mode, so the audit runs from the
 * daily security task, `php spark security:audit` or CI (which can store its own JSON output with
 * `security:audit --from=<file>`), never from a page. The result is kept in
 * `<ProductionCheck::$path>/composer-audit.json` (0640): `checkedAt` (UTC), `advisories` (count),
 * `packages` (names with advisories), `abandoned`, `error`.
 *
 * Composer is a documented exception to the OutboundClient (like `git` and `composer` in
 * `app:update`): it reaches packagist.org with its own client.
 */
final class ComposerAudit
{
    /**
     * @param (Closure(list<string>, string): array{code: int, output: string})|null $runner runs a command (tests)
     */
    public function __construct(
        private readonly ?ProductionCheck $config = null,
        private readonly ?Closure $runner = null,
        private readonly string $root = ROOTPATH,
    ) {
    }

    public function file(): string
    {
        return rtrim(($this->config ?? config(ProductionCheck::class))->path, '/') . '/composer-audit.json';
    }

    /**
     * Runs `composer audit --locked --format=json` and stores the result.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $config = $this->config ?? config(ProductionCheck::class);
        $result = ($this->runner ?? self::process(...))([$config->composer, 'audit', '--locked', '--no-interaction', '--format=json', '--working-dir=' . rtrim($this->root, '/')], $this->root);

        return $this->store($result['output'], $result['code']);
    }

    /**
     * Parses `composer audit --format=json` output (from this server or CI) and stores it.
     *
     * @return array<string, mixed>
     */
    public function store(string $json, int $code = 0): array
    {
        $start = strpos($json, '{');
        $data  = $start === false ? null : json_decode(substr($json, $start), true);

        if (! is_array($data) || ! array_key_exists('advisories', $data)) {
            $status = ['checkedAt' => gmdate('Y-m-d H:i:s'), 'advisories' => 0, 'packages' => [], 'abandoned' => [], 'error' => trim(mb_substr($json, 0, 500)) !== '' ? trim(mb_substr($json, 0, 500)) : 'composer audit exited with ' . $code];
        } else {
            $advisories = is_array($data['advisories']) ? $data['advisories'] : [];
            $count      = 0;

            foreach ($advisories as $list) {
                $count += is_array($list) ? count($list) : 0;
            }

            $status = [
                'checkedAt'  => gmdate('Y-m-d H:i:s'),
                'advisories' => $count,
                'packages'   => array_map('strval', array_keys($advisories)),
                'abandoned'  => array_map('strval', array_keys(is_array($data['abandoned'] ?? null) ? $data['abandoned'] : [])),
                'error'      => null,
            ];
        }

        $this->write($status);

        return $status;
    }

    /**
     * @return array<string, mixed>|null null before the first audit
     */
    public function last(): ?array
    {
        $file = $this->file();

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function write(array $status): void
    {
        $file = $this->file();
        $dir  = dirname($file);

        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($tmp, 0640);
        rename($tmp, $file);
    }

    /**
     * @param list<string> $command
     *
     * @return array{code: int, output: string}
     */
    private static function process(array $command, string $cwd): array
    {
        $errors  = (string) tempnam(sys_get_temp_dir(), 'kpaudit');
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $errors, 'w']], $pipes, $cwd);

        if (! is_resource($process)) {
            @unlink($errors);

            return ['code' => 127, 'output' => 'Could not start ' . $command[0]];
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $code = proc_close($process);

        if (trim($output) === '') {
            $output = (string) @file_get_contents($errors);
        }

        @unlink($errors);

        return ['code' => $code, 'output' => $output];
    }
}
