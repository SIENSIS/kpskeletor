<?php

declare(strict_types=1);

namespace Modules\Core\Routing;

use CodeIgniter\Events\Events;
use Modules\Core\Install\EnvWriter;
use Modules\Core\Maintenance\MaintenanceMode;
use RuntimeException;
use Throwable;

/**
 * Changes the back-office prefix of an installed site (`php spark app:backend-path`, D-40-01):
 * maintenance on, `app.backendPath` in `.env`, stored links (the Core's notifications and the
 * modules through the event `backendPathChanged`), caches, a security log entry, maintenance
 * off. Never from the back office.
 */
final class BackendPathChanger
{
    public const MAINTENANCE_REASON = 'backend-path';

    public function __construct(
        private readonly string $envPath = ROOTPATH . '.env',
        private readonly MaintenanceMode $maintenance = new MaintenanceMode(),
    ) {
    }

    /**
     * Why the path cannot be the new prefix (format, reserved, the current one), or null.
     */
    public function error(string $new): ?string
    {
        if ($new === BackendPath::current()) {
            return lang('Core.backendPath.same', [$new]);
        }

        return BackendPath::error($new, BackendPath::routedSegments());
    }

    /**
     * The `.env` line to copy by hand.
     */
    public static function envLine(string $new): string
    {
        return 'app.backendPath = ' . EnvWriter::quote($new);
    }

    public function envWritable(): bool
    {
        return is_file($this->envPath) ? is_writable($this->envPath) : is_writable(dirname($this->envPath));
    }

    /**
     * Runs the change. On failure maintenance mode stays on and the exception says which step
     * failed (`BackendPathChangeFailed`).
     *
     * @return array<string, int> rows rewritten (what => count)
     */
    public function change(string $new): array
    {
        $old   = BackendPath::current();
        $error = $this->error($new);

        if ($error !== null) {
            throw new RuntimeException($error);
        }

        $wasOn = $this->maintenance->isOn();

        if (! $wasOn) {
            $this->maintenance->enable(self::MAINTENANCE_REASON);
        }

        $step = 'env';

        try {
            $content = is_file($this->envPath) ? (string) file_get_contents($this->envPath) : '';
            (new EnvWriter())->write($this->envPath, EnvWriter::withValue($content, 'app.backendPath', $new));

            $step  = 'links';
            $event = new BackendPathChanged($old, $new);
            $event->rewriteColumn('notifications', 'data', true);
            Events::trigger('backendPathChanged', $event);

            // The running process follows the new prefix from now on.
            config('App')->backendPath = $new;

            $step = 'cache';
            cache()->clean();

            security_log('Core', 'backend_path_changed', [
                'resource' => 'backend-path',
                'summary'  => $old . ' → ' . $new,
                'changes'  => ['old' => $old, 'new' => $new, 'source' => 'console', 'rows' => $event->summary()],
            ]);
        } catch (Throwable $e) {
            throw new BackendPathChangeFailed($step, $old, $new, $e);
        }

        if (! $wasOn) {
            $this->maintenance->disable();
        }

        return $event->summary();
    }
}
