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
 * database while the mode is on. The Backup module (restores) and the updater turn it on and off
 * through this class; administrators turn it on by hand from Settings › Actions or with
 * `php spark app:maintenance` (`enableByHand()`, D-28-02): then a message can be shown, the
 * login paths stay open and signed-in holders of `maintenance.manage` (superadmins always) keep
 * using the site (the `bypass` flag). A
 * restore or an update never sets `bypass`: the database may be replaced meanwhile.
 */
final class MaintenanceMode
{
    /** Turns it on and off by hand and keeps using the site meanwhile (no default grant; D-28-02). */
    public const PERMISSION = 'maintenance.manage';

    /** The reason recorded when it is turned on by hand. */
    public const REASON_MANUAL = 'manual';

    /** Longest message shown on the maintenance page. */
    public const MESSAGE_MAX = 500;

    /**
     * Paths still served while it is on by hand, so a superadmin can sign in (Shield's login,
     * logout, magic link, two-factor and re-authentication screens).
     */
    public const LOGIN_PATHS = ['login', 'login/*', 'logout', 'auth/*'];

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
     * @param list<string> $allow   Paths still served (CodeIgniter filter patterns, e.g. `progress/backup/*`)
     * @param string       $message Shown on the maintenance page (plain text, empty = the default text)
     * @param bool         $bypass  Holders of `maintenance.manage` keep using the site (only when the database is not replaced)
     */
    public function enable(string $reason, array $allow = [], string $message = '', bool $bypass = false): void
    {
        $data = json_encode([
            'reason'  => $reason,
            'since'   => Time::now('UTC')->format('Y-m-d H:i:s'),
            'allow'   => array_values(array_filter($allow, static fn ($path): bool => $path !== '')),
            'message' => self::cleanMessage($message),
            'bypass'  => $bypass,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        $tmp = $this->file() . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $data, LOCK_EX);
        @chmod($tmp, 0640);
        rename($tmp, $this->file());
    }

    /**
     * Turned on by hand: the login paths stay open and holders of `maintenance.manage` bypass it.
     */
    public function enableByHand(string $message = ''): void
    {
        $this->enable(self::REASON_MANUAL, self::LOGIN_PATHS, $message, true);
    }

    /**
     * One line of plain text, at most MESSAGE_MAX characters.
     */
    public static function cleanMessage(string $message): string
    {
        $message = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $message));

        return mb_substr($message, 0, self::MESSAGE_MAX);
    }

    public function disable(): void
    {
        if (is_file($this->file())) {
            @unlink($this->file());
        }
    }

    /**
     * @return array{reason: string, since: string, allow: list<string>, message: string, bypass: bool}|null
     */
    public function state(): ?array
    {
        if (! $this->isOn()) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($this->file()), true);
        $data = is_array($data) ? $data : [];

        return [
            'reason'  => is_string($data['reason'] ?? null) ? $data['reason'] : '',
            'since'   => is_string($data['since'] ?? null) ? $data['since'] : '',
            'allow'   => array_values(array_filter(is_array($data['allow'] ?? null) ? $data['allow'] : [], 'is_string')),
            'message' => is_string($data['message'] ?? null) ? self::cleanMessage($data['message']) : '',
            'bypass'  => ($data['bypass'] ?? false) === true,
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
