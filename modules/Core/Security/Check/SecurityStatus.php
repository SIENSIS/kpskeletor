<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

use Modules\Core\Config\ProductionCheck;
use Modules\Core\Notifications\Types\SecurityCheckFailed;
use Throwable;

/**
 * The cached results of the security checks (`<ProductionCheck::$path>/status.json`, 0640),
 * read by the Security status page and its dashboard widget, refreshed by the daily task
 * (SecurityCheckTask), the page's "check again" button and `security:check` (full runs only).
 * The last external probe is kept apart (`probe.json`). When a check turns red, the
 * superadmins are notified (SecurityCheckFailed), once per check until it recovers.
 */
final class SecurityStatus
{
    public function __construct(private readonly ?ProductionCheck $config = null)
    {
    }

    public function file(string $name = 'status'): string
    {
        return rtrim(($this->config ?? config(ProductionCheck::class))->path, '/') . '/' . $name . '.json';
    }

    public function read(string $name = 'status'): ?SecurityReport
    {
        $file = $this->file($name);

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? SecurityReport::fromArray($data) : null;
    }

    /**
     * Stores a full report and notifies the superadmins of the checks that turned red since the
     * last stored report.
     *
     * @return list<string> ids of the checks that turned red
     */
    public function store(SecurityReport $report, bool $notify = true): array
    {
        $previous = $this->read();
        $before   = $previous?->errorIds() ?? [];
        $new      = array_values(array_diff($report->errorIds(), $before));

        $this->write('status', $report->toArray());

        if ($notify && $new !== []) {
            $titles = [];

            foreach ($report->results() as $result) {
                if (in_array($result->id, $new, true)) {
                    $titles[$result->id] = $result->title;
                }
            }

            try {
                notifyGroup('superadmin', new SecurityCheckFailed(['checks' => array_values($titles), 'errors' => count($report->errorIds())]));
            } catch (Throwable) {
                // notifications are not installed yet: the page and the command still show it
            }
        }

        return $new;
    }

    public function storeProbe(SecurityReport $report): void
    {
        $this->write('probe', $report->toArray());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function write(string $name, array $data): void
    {
        $file = $this->file($name);
        $dir  = dirname($file);

        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX);
        @chmod($tmp, 0640);
        rename($tmp, $file);
    }
}
