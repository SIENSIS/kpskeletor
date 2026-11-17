<?php

declare(strict_types=1);

namespace Modules\Core\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Options of `security:check` and the Security status page (docs/security.md, "Production
 * checklist"). Overridden by `app/Config/ProductionCheck.php` and `.env`
 * (`productioncheck.webUser = www-data`); read with `config('ProductionCheck')`.
 */
class ProductionCheck extends BaseConfig
{
    /**
     * Where the cached results (`status.json`) and the last `composer audit` result
     * (`composer-audit.json`) are kept (0640, never served).
     */
    public string $path = WRITEPATH . 'security';

    /**
     * The system user the web server (PHP-FPM, Apache) runs as, e.g. `www-data`. From the
     * console the check computes what that user may write from the owner, group and mode of
     * each path (POSIX extension). Empty: the console only checks that nothing is writable by
     * every user, and the Security status page (which runs as the web user) checks the rest.
     */
    public string $webUser = '';

    /**
     * Paths (relative to the project root) the web server may write. Everything else listed in
     * $readOnlyPaths must not be writable by it.
     *
     * @var list<string>
     */
    public array $writablePaths = ['writable', 'public/assets/modules', 'public/assets/core', 'public/themes', 'public/uploads'];

    /**
     * Paths (relative to the project root; '' = the root itself) the web server must not write.
     *
     * @var list<string>
     */
    public array $readOnlyPaths = ['', '.env', 'app', 'modules', 'themes', 'vendor', 'public', 'public/index.php', 'public/.htaccess'];

    /** The Composer executable the daily task and `security:audit` run (`composer audit`). */
    public string $composer = 'composer';

    /** Days after which the last `composer audit` result is reported as stale (warning). */
    public int $auditMaxAgeDays = 7;

    /** Most files scanned for secrets (tracked files) and permissions (`writable/`). */
    public int $maxScannedFiles = 20000;

    /** Seconds each request of `--probe` may take. */
    public int $probeTimeout = 10;

    /**
     * The login form `--probe` sends wrong passwords to (with a made-up address under
     * `example.invalid`) until it answers 429; empty: not probed. A few failed attempts from the
     * server's own address count towards the per-IP limit for a while.
     */
    public string $probeLoginPath = 'login';

    /** Most wrong passwords `--probe` sends before reporting the login as not throttled. */
    public int $probeLoginAttempts = 12;
}
