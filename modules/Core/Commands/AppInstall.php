<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Install\CliPrompt;
use Modules\Core\Install\Installer;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\InstallOptions;

/**
 * php spark app:install [options]
 *
 * Interactive by default; every value can be given as an option, and `--no-interaction` (or a
 * non-terminal input) never asks: missing values take their defaults or stop the installation.
 * Passwords are never options (they would stay in the shell history): they are asked without
 * echo, or read from the environment variables INSTALL_DB_PASSWORD and INSTALL_ADMIN_PASSWORD.
 */
final class AppInstall extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'app:install';
    protected $description = 'Installs the application: .env with new keys, modules:sync, the first superadmin (and 2FA), demo data, the cron line and the lock file.';
    protected $usage       = 'app:install [--env=production|development] [--url=<base URL>] [--db=sqlite|mysql] [--db-host=…] [--db-port=…] [--db-name=…] [--db-user=…] [--db-prefix=…] [--admin=<username>] [--email=<email>] [--demo[=<Module,Module>]] [--2fa=none|email|totp] [--keep-env] [--no-interaction]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--env'            => 'production (default) or development.',
        '--url'            => 'The site base URL (e.g. https://intranet.example.com/).',
        '--db'             => 'Database driver: mysql (MariaDB/MySQL, default) or sqlite.',
        '--db-host'        => 'Database host (default localhost).',
        '--db-port'        => 'Database port (default 3306).',
        '--db-name'        => 'Database name (SQLite: file name in writable/, default database.db).',
        '--db-user'        => 'Database user.',
        '--db-prefix'      => 'Table prefix (default none).',
        '--admin'          => 'Username of the first superadmin (default admin).',
        '--email'          => 'Email of the first superadmin.',
        '--demo'           => 'Load demo data: of every module, or of the listed ones (with the modules they need).',
        '--2fa'            => 'Two-factor authentication for the superadmin: none, email or totp (asked when interactive).',
        '--keep-env'       => 'Use the existing .env (development): only missing keys are added.',
        '--no-interaction' => 'Never ask: use the options, the environment variables and the defaults.',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $interactive = ModulesSync::option($params, 'no-interaction') === null
            && (! function_exists('posix_isatty') || posix_isatty(STDIN));
        $prompt    = new CliPrompt($params, $interactive);
        $installer = new Installer();
        $keepEnv   = ModulesSync::option($params, 'keep-env') !== null;
        $state     = $installer->state();

        if ($state->isLocked()) {
            CLI::error(lang('Install.error.configured'));

            return EXIT_ERROR;
        }

        if ($state->hasEnv() && ! $keepEnv) {
            if (! $interactive || $prompt->ask(lang('Install.cli.keepEnv'), ['y', 'n']) !== 'y') {
                CLI::error(lang('Install.cli.envExists'));

                return EXIT_ERROR;
            }

            $keepEnv = true;
        }

        CLI::write(lang('Install.cli.title'), 'green');

        $options = $this->collect($prompt, $keepEnv && $state->hasEnv());

        try {
            $errors = $installer->validate($options, ! ($keepEnv && $state->hasEnv()));

            if ($errors !== []) {
                throw new InstallException(lang('Install.error.invalid'), $errors);
            }

            CLI::write(lang('Install.cli.installing'));
            $result = $installer->install($options, $prompt, $keepEnv);
        } catch (InstallException $e) {
            CLI::error($e->getMessage());

            foreach ($e->errors as $field => $message) {
                CLI::error('  - ' . $field . ': ' . $message);
            }

            return EXIT_ERROR;
        }

        if ($result->envCompleted !== []) {
            CLI::write(lang('Install.cli.envCompleted', [implode(', ', $result->envCompleted)]), 'yellow');
        } elseif ($result->envWritten) {
            CLI::write(lang('Install.cli.envWritten', [$state->envPath()]), 'green');
        } elseif ($result->env !== '') {
            CLI::write(lang('Install.cli.envCopy', [$state->envPath()]), 'yellow');
            CLI::write($result->env);
        }

        if ($result->demoError !== '') {
            CLI::write(lang('Install.web.demoFailed', [$result->demoError]), 'yellow');
        }

        if ($result->demoLoaded !== []) {
            CLI::write(lang('Install.cli.demoLoaded', [implode(', ', $result->demoLoaded)]), 'green');
        }

        ModulesDemo::writeSecrets($result->demoSecrets);

        CLI::write(lang('Install.cli.superadmin', [$options->adminUsername, $options->adminEmail]), 'green');
        CLI::newLine();
        CLI::write(lang('Install.cli.cron'));
        CLI::write('  ' . $result->cronLine, 'yellow');
        CLI::newLine();
        CLI::write(lang('Install.cli.securityCheck'));
        CLI::write(lang('Install.cli.done', [$result->lockPath]), 'green');

        return EXIT_SUCCESS;
    }

    private function collect(CliPrompt $prompt, bool $keepEnv): InstallOptions
    {
        $options = new InstallOptions();

        // With a kept .env the environment is the one it sets.
        $options->environment = $keepEnv
            ? (ENVIRONMENT === 'production' ? 'production' : 'development')
            : $this->value($prompt, 'env', lang('Install.cli.askEnvironment'), 'production', InstallOptions::ENVIRONMENTS);
        $options->baseURL     = $keepEnv ? '' : $this->value($prompt, 'url', lang('Install.cli.askUrl'), '');

        if (! $keepEnv) {
            $options->dbDriver = InstallOptions::driver($this->value($prompt, 'db', lang('Install.cli.askDriver'), 'mysql', ['mysql', 'sqlite']));

            if ($options->isSqlite()) {
                $options->dbName = $this->value($prompt, 'db-name', lang('Install.cli.askSqliteName'), 'database.db');
            } else {
                $options->dbHost     = $this->value($prompt, 'db-host', lang('Install.cli.askHost'), 'localhost');
                $options->dbPort     = (int) $this->value($prompt, 'db-port', lang('Install.cli.askPort'), '3306');
                $options->dbName     = $this->value($prompt, 'db-name', lang('Install.cli.askDbName'), '');
                $options->dbUser     = $this->value($prompt, 'db-user', lang('Install.cli.askDbUser'), '');
                $options->dbPassword = $this->password($prompt, 'INSTALL_DB_PASSWORD', lang('Install.cli.askDbPassword'), false);
            }

            $options->dbPrefix = $this->value($prompt, 'db-prefix', lang('Install.cli.askPrefix'), '');
        }

        $options->adminUsername = $this->value($prompt, 'admin', lang('Install.cli.askUsername'), 'admin');
        $options->adminEmail    = $this->value($prompt, 'email', lang('Install.cli.askEmail'), '');
        $options->adminPassword = $this->password($prompt, 'INSTALL_ADMIN_PASSWORD', lang('Install.cli.askPassword'), true);
        $options->demo          = $this->demo($prompt, $options->environment);

        return $options;
    }

    /**
     * @param list<string> $choices
     */
    private function value(CliPrompt $prompt, string $option, string $question, string $default, array $choices = []): string
    {
        $given = $prompt->option($option);

        if ($given !== null && $given !== '') {
            return trim($given);
        }

        if (! $prompt->interactive()) {
            return $default;
        }

        if ($choices !== []) {
            // CLI::prompt() takes the first choice as the default.
            $choices = array_values(array_unique([$default, ...$choices]));

            return trim($prompt->ask($question, $choices));
        }

        return trim($prompt->ask($question, [], $default === '' ? null : $default));
    }

    private function password(CliPrompt $prompt, string $variable, string $question, bool $confirm): string
    {
        $value = getenv($variable);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (! $prompt->interactive()) {
            return '';
        }

        for ($try = 0; $try < 3; $try++) {
            $password = $prompt->secret($question);

            if (! $confirm || $password === $prompt->secret(lang('Install.cli.askPasswordAgain'))) {
                return $password;
            }

            CLI::error(lang('Install.cli.passwordMismatch'));
        }

        return '';
    }

    /**
     * @return list<string>|null
     */
    private function demo(CliPrompt $prompt, string $environment): ?array
    {
        $given = $prompt->option('demo');

        if ($given !== null) {
            return $given === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $given))));
        }

        if (! $prompt->interactive() || ($environment === 'production' && setting('Packages.allowDemoInProduction') !== true)) {
            return null;
        }

        if ($prompt->ask(lang('Install.cli.askDemo'), ['n', 'y']) !== 'y') {
            return null;
        }

        $modules = $prompt->ask(lang('Install.cli.askDemoModules', [implode(', ', $this->demoModules())]), [], 'all');

        return in_array(strtolower(trim($modules)), ['', 'all'], true) ? [] : array_values(array_filter(array_map('trim', explode(',', $modules))));
    }

    /**
     * Modules on disk that ship a demo seeder (the registry does not exist yet).
     *
     * @return list<string>
     */
    private function demoModules(): array
    {
        $modules = [];

        foreach (glob(ROOTPATH . 'modules/*/Database/Seeds/*DemoSeeder.php') ?: [] as $file) {
            $module = basename(dirname($file, 3));

            if (basename($file) === $module . 'DemoSeeder.php') {
                $modules[] = $module;
            }
        }

        return $modules;
    }
}
