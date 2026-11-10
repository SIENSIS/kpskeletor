<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * What the installer needs, from `app:install` options/prompts or the web installer's form.
 * `validate()` returns the problems as field => message.
 */
final class InstallOptions
{
    public const ENVIRONMENTS = ['production', 'development'];

    public const DRIVERS = ['SQLite3', 'MySQLi'];

    /**
     * @param list<string>|null $demo Modules whose demo data is loaded (null: none; []: every module with demo data)
     */
    public function __construct(
        public string $environment = 'production',
        public string $baseURL = '',
        public string $dbDriver = 'MySQLi',
        public string $dbHost = 'localhost',
        public int $dbPort = 3306,
        public string $dbName = '',
        public string $dbUser = '',
        public string $dbPassword = '',
        public string $dbPrefix = '',
        public string $adminUsername = 'admin',
        public string $adminEmail = '',
        public string $adminPassword = '',
        public ?array $demo = null,
    ) {
    }

    /**
     * Normalises a driver name given by a person (`sqlite`, `mysql`, `mariadb`…).
     */
    public static function driver(string $value): string
    {
        return match (strtolower(trim($value))) {
            'sqlite', 'sqlite3' => 'SQLite3',
            'mysql', 'mysqli', 'mariadb' => 'MySQLi',
            default => trim($value),
        };
    }

    public function isSqlite(): bool
    {
        return $this->dbDriver === 'SQLite3';
    }

    /**
     * @return array<string, string> field => message
     */
    public function validate(bool $checkDatabase = true): array
    {
        $errors = [];

        if (! in_array($this->environment, self::ENVIRONMENTS, true)) {
            $errors['environment'] = lang('Install.invalid.environment');
        }

        if ($this->baseURL !== '' && (filter_var($this->baseURL, FILTER_VALIDATE_URL) === false || preg_match('#^https?://[^/?\#]+(/[^?\#]*)?$#i', $this->baseURL) !== 1)) {
            $errors['baseURL'] = lang('Install.invalid.baseURL');
        }

        if ($checkDatabase) {
            $errors += $this->validateDatabase();
        }

        if (preg_match('/^[A-Za-z0-9._-]{3,30}$/', $this->adminUsername) !== 1) {
            $errors['adminUsername'] = lang('Install.invalid.username');
        }

        if (filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['adminEmail'] = lang('Install.invalid.email');
        }

        $min = (int) config('Auth')->minimumPasswordLength;

        if (mb_strlen($this->adminPassword) < $min) {
            $errors['adminPassword'] = lang('Install.invalid.passwordLength', [$min]);
        } elseif (! EnvWriter::acceptable($this->adminPassword)) {
            $errors['adminPassword'] = lang('Install.invalid.characters');
        }

        if ($this->demo !== null) {
            foreach ($this->demo as $module) {
                if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) !== 1) {
                    $errors['demo'] = lang('Install.invalid.demo', [$module]);
                    break;
                }
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function validateDatabase(): array
    {
        $errors = [];

        if (! in_array($this->dbDriver, self::DRIVERS, true)) {
            return ['dbDriver' => lang('Install.invalid.driver')];
        }

        if ($this->isSqlite()) {
            // A file name inside writable/ (CodeIgniter's SQLite3 driver adds WRITEPATH).
            if (preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]{0,63}$/', $this->dbName) !== 1 || str_contains($this->dbName, '..')) {
                $errors['dbName'] = lang('Install.invalid.sqliteName');
            }
        } else {
            if (preg_match('/^[A-Za-z0-9.:_-]{1,253}$/', $this->dbHost) !== 1) {
                $errors['dbHost'] = lang('Install.invalid.host');
            }

            if ($this->dbPort < 1 || $this->dbPort > 65535) {
                $errors['dbPort'] = lang('Install.invalid.port');
            }

            if (preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $this->dbName) !== 1) {
                $errors['dbName'] = lang('Install.invalid.dbName');
            }

            if (preg_match('/^[A-Za-z0-9_.@-]{1,80}$/', $this->dbUser) !== 1) {
                $errors['dbUser'] = lang('Install.invalid.dbUser');
            }

            if (! EnvWriter::acceptable($this->dbPassword)) {
                $errors['dbPassword'] = lang('Install.invalid.characters');
            }
        }

        if (preg_match('/^[A-Za-z0-9_]{0,20}$/', $this->dbPrefix) !== 1) {
            $errors['dbPrefix'] = lang('Install.invalid.prefix');
        }

        return $errors;
    }

    /**
     * The `database.default.*` values of `.env`.
     *
     * @return array<string, string>
     */
    public function databaseEnv(): array
    {
        if ($this->isSqlite()) {
            return [
                'database.default.DBDriver' => 'SQLite3',
                'database.default.database' => $this->dbName,
                'database.default.DBPrefix'  => $this->dbPrefix,
            ];
        }

        return [
            'database.default.hostname' => $this->dbHost,
            'database.default.database' => $this->dbName,
            'database.default.username' => $this->dbUser,
            'database.default.password' => $this->dbPassword,
            'database.default.DBDriver' => 'MySQLi',
            'database.default.DBPrefix' => $this->dbPrefix,
            'database.default.port'     => (string) $this->dbPort,
        ];
    }
}
