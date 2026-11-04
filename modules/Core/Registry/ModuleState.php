<?php

declare(strict_types=1);

namespace Modules\Core\Registry;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Modules\Core\Cache\CoreCache;

/**
 * Which modules are enabled, read from the registry (`modules` table) and cached in the
 * "modules" scope. Before the first `modules:sync` (no table yet) every module counts as enabled.
 */
final class ModuleState
{
    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly CoreCache $cache = new CoreCache(),
    ) {
    }

    /**
     * Names of the registered modules that are disabled.
     *
     * @return list<string>
     */
    public function disabled(): array
    {
        $disabled = $this->cache->remember('modules', 'disabled', function (): ?array {
            try {
                $rows = $this->db()->table('modules')->select('name')->where('enabled', 0)->orderBy('name')->get()->getResultArray();
            } catch (\Throwable) {
                return null; // not installed yet: do not cache
            }

            return array_map(static fn (array $row): string => (string) $row['name'], $rows);
        });

        if ($disabled === null) {
            $this->cache->invalidate('modules');

            return [];
        }

        return $disabled;
    }

    public function isEnabled(string $module): bool
    {
        return ! in_array($module, $this->disabled(), true);
    }

    /**
     * The module a class belongs to ("\Modules\News\Controllers\Posts" → "News"), or null.
     */
    public static function moduleOf(string $class): ?string
    {
        if (preg_match('/^\\\\?Modules\\\\([A-Za-z][A-Za-z0-9]*)\\\\/', $class, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
