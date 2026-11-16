<?php

declare(strict_types=1);

namespace Modules\Core\Maintenance;

use CodeIgniter\I18n\Time;

/**
 * Maintenance mode (docs/security.md, "Maintenance mode"; D-27-02): while it is on, every web request answers 503 (the
 * `maintenance` global filter) except the paths listed when it was turned on (e.g. the progress
 * of a restore); the console keeps working and `schedule:run` skips.
 *
 * The state is a file (`writable/maintenance.json`), not the database: a restore replaces the
 * database while the mode is on. Session 28 adds the setting and the screen; the Backup module
 * (restores) and the updater turn it on and off through this class.
 */
final class MaintenanceMode
{
    public function __construct(private readonly ?string $file = null)
    {
    }

    public function file(): string
    {
        return $this->file ?? WRITEPATH . 'maintenance.json';
    }

    public function isOn(): bool
    {
        return is_file($this->file());
    }

    /**
     * Turns it on.
     *
     * @param string       $reason Who turned it on (e.g. `backup.restore`), for the logs and the screen
     * @param list<string> $allow  Paths still served (CodeIgniter filter patterns, e.g. `progress/backup/*`)
     */
    public function enable(string $reason, array $allow = []): void
    {
        $data = json_encode([
            'reason' => $reason,
            'since'  => Time::now('UTC')->format('Y-m-d H:i:s'),
            'allow'  => array_values(array_filter($allow, static fn ($path): bool => $path !== '')),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $tmp = $this->file() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $data, LOCK_EX);
        @chmod($tmp, 0640);
        rename($tmp, $this->file());
    }

    public function disable(): void
    {
        if (is_file($this->file())) {
            @unlink($this->file());
        }
    }

    /**
     * @return array{reason: string, since: string, allow: list<string>}|null
     */
    public function state(): ?array
    {
        if (! $this->isOn()) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->file()), true);
        $data = is_array($data) ? $data : [];

        return [
            'reason' => is_string($data['reason'] ?? null) ? $data['reason'] : '',
            'since'  => is_string($data['since'] ?? null) ? $data['since'] : '',
            'allow'  => array_values(array_filter(is_array($data['allow'] ?? null) ? $data['allow'] : [], 'is_string')),
        ];
    }

    /**
     * Whether a request path (without the leading slash) is still served.
     */
    public function allows(string $path): bool
    {
        $path = trim($path, '/');

        foreach ($this->state()['allow'] ?? [] as $pattern) {
            $regex = '#\A' . str_replace('\*', '.*', preg_quote(trim($pattern, '/'), '#')) . '\z#';

            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
