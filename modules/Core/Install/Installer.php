<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Events\Events;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\Database;
use Modules\Core\Config\Version;
use Modules\Core\Demo\DemoDataException;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Modules\ModuleSynchronizer;
use Modules\Core\Modules\SyncFailedException;
use Throwable;

/**
 * The installation service shared by `php spark app:install` and the web installer:
 *
 * 1. refuses when the application is configured (`.env` or `writable/install.lock`; the console
 *    may keep a developer's `.env`, see `$keepEnv`) or the database already has tables;
 * 2. generates the encryption key and `app.maskKey` and builds the `.env` content
 *    (`CI_ENVIRONMENT=production` unless development is chosen);
 * 3. applies it to the running process (RuntimeConfig) and checks the database;
 * 4. runs `modules:sync` (migrations, seeds, permissions, settings, assets);
 * 5. creates the first superadmin and fires `install.superadmin` (Auth offers 2FA);
 * 6. loads the chosen demo data (with the modules they depend on first);
 * 7. writes `.env` (or leaves it for the operator to copy when the root is not writable) and the
 *    lock file, last, so a failed installation can be repeated.
 */
final class Installer
{
    /** Fired with (User $superadmin, ?InstallPrompt $prompt) once the first superadmin exists. */
    public const EVENT_SUPERADMIN = 'install.superadmin';

    private readonly InstallState $state;

    /**
     * @param Closure(): ModuleSynchronizer|null $synchronizer
     */
    public function __construct(
        ?InstallState $state = null,
        private readonly EnvWriter $env = new EnvWriter(),
        private readonly KeyGenerator $keys = new KeyGenerator(),
        private readonly RuntimeConfig $runtime = new RuntimeConfig(),
        private readonly ?Closure $synchronizer = null,
    ) {
        $this->state = $state ?? service('installstate');
    }

    public function state(): InstallState
    {
        return $this->state;
    }

    /**
     * The cron line of the scheduler for this installation.
     */
    public static function cronLine(string $rootPath = ROOTPATH): string
    {
        return '* * * * * cd ' . escapeshellarg(rtrim($rootPath, '/\\')) . ' && ' . PHP_BINARY . ' spark schedule:run >> /dev/null 2>&1';
    }

    /**
     * Whether the database of the default connection has tables (any prefix).
     *
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public static function databaseHasTables(?BaseConnection $db = null): bool
    {
        $db ??= Database::connect();

        return $db->listTables(false) !== [];
    }

    /**
     * Problems with the options (field => message), including the password rules of Shield.
     *
     * @return array<string, string>
     */
    public function validate(InstallOptions $options, bool $checkDatabase = true): array
    {
        $errors = $options->validate($checkDatabase);

        if (! isset($errors['adminPassword'])) {
            $user   = new User(['username' => $options->adminUsername, 'email' => $options->adminEmail]);
            $result = service('passwords')->check($options->adminPassword, $user);

            if (! $result->isOK()) {
                $errors['adminPassword'] = (string) $result->reason();
            }
        }

        if ($options->environment === 'production' && $options->demo !== null && setting('Packages.allowDemoInProduction') !== true) {
            $errors['demo'] = lang('Install.invalid.demoProduction');
        }

        return $errors;
    }

    /**
     * The `.env` values of a new installation.
     *
     * @return array<string, string>
     */
    public function envValues(InstallOptions $options): array
    {
        $values = ['CI_ENVIRONMENT' => $options->environment];

        if ($options->baseURL !== '') {
            $values['app.baseURL'] = rtrim($options->baseURL, '/') . '/';
        }

        return $values + [
            'app.maskKey'    => $this->keys->maskKey(),
            'encryption.key' => $this->keys->encryptionKey(),
        ] + $options->databaseEnv();
    }

    /**
     * Installs the application.
     *
     * @param bool $keepEnv Console only: an existing `.env` (no lock file) is kept and used; the
     *                      database options are ignored and missing keys are added to it.
     *
     * @throws InstallException
     */
    public function install(InstallOptions $options, ?InstallPrompt $prompt = null, bool $keepEnv = false): InstallResult
    {
        $result = new InstallResult();

        if ($this->state->isLocked() || ($this->state->hasEnv() && ! $keepEnv)) {
            throw new InstallException(lang('Install.error.configured'));
        }

        $keepEnv = $keepEnv && $this->state->hasEnv();
        $errors  = $this->validate($options, ! $keepEnv);

        if ($errors !== []) {
            throw new InstallException(lang('Install.error.invalid'), $errors);
        }

        // 2-3. Keys and .env content, applied to this process.
        if ($keepEnv) {
            $keys = [];

            if (trim((string) config('App')->maskKey) === '') {
                $keys['app.maskKey'] = $this->keys->maskKey();
            }

            if ((string) config('Encryption')->key === '') {
                $keys['encryption.key'] = $this->keys->encryptionKey();
            }

            $result->envCompleted = $keys === [] ? [] : $this->env->complete($this->state->envPath(), $keys);
            $values               = $keys;
        } else {
            $values      = $this->envValues($options);
            $result->env = $this->env->render($values, 'Written by the installer on ' . Time::now()->toDateTimeString() . ' (Core ' . Version::CORE . ').');
        }

        if ($values !== []) {
            $this->runtime->apply($values);
        }

        $this->checkDatabase();

        // 4. modules:sync.
        try {
            $report = $this->synchronizer()->sync();
        } catch (SyncFailedException $e) {
            throw new InstallException(lang('Install.error.sync') . ' ' . implode(' ', $e->errors));
        }

        $result->syncLines = $report->lines();

        foreach ($result->syncLines as $line) {
            $prompt?->write('  ' . $line);
        }

        // 5. The first superadmin.
        $user                 = $this->createSuperadmin($options);
        $result->superadminId = (int) $user->id;

        // 6. Demo data.
        // A failure here does not undo the installation: it is reported and the demo data can be
        // loaded later with `php spark modules:demo <Name>`.
        if ($options->demo !== null) {
            try {
                $this->loadDemo($options->demo, $report->order, $options->environment, $result);
            } catch (DemoDataException|InstallException $e) {
                $result->demoError = $e->getMessage();
            }
        }

        activity_log('Core', 'installed', ['user_id' => $user->id, 'resource' => 'modules', 'summary' => Version::CORE]);

        Events::trigger(self::EVENT_SUPERADMIN, $user, $prompt);

        // 7. .env and the lock file, last.
        if (! $keepEnv) {
            if ($this->state->rootWritable()) {
                $this->env->write($this->state->envPath(), $result->env);
                $result->envWritten = true;
            }
        }

        $this->state->writeLock([
            'installed_at' => Time::now()->toDateTimeString(),
            'version'      => Version::CORE,
            'environment'  => $options->environment,
        ]);

        $result->lockPath = $this->state->lockPath();
        $result->cronLine = self::cronLine();

        return $result;
    }

    /**
     * Connects with the configured database and refuses a non-empty one.
     */
    private function checkDatabase(): void
    {
        try {
            $db = Database::connect();
            $db->initialize();
        } catch (Throwable $e) {
            throw new InstallException(lang('Install.error.database', [$e->getMessage()]));
        }

        if (self::databaseHasTables($db)) {
            throw new InstallException(lang('Install.error.notEmpty'));
        }
    }

    private function synchronizer(): ModuleSynchronizer
    {
        return $this->synchronizer !== null ? ($this->synchronizer)() : new ModuleSynchronizer();
    }

    private function createSuperadmin(InstallOptions $options): User
    {
        $users = model(UserModel::class);
        $user  = new User([
            'username' => $options->adminUsername,
            'email'    => mb_strtolower(trim($options->adminEmail)),
            'password' => $options->adminPassword,
            'active'   => 1,
        ]);

        try {
            $users->save($user);
        } catch (Throwable $e) {
            throw new InstallException(lang('Install.error.superadmin', [$e->getMessage()]));
        }

        $user = $users->findById((int) $users->getInsertID());

        if (! $user instanceof User) {
            throw new InstallException(lang('Install.error.superadmin', ['']));
        }

        $user->addGroup('superadmin');

        security_log('Core', 'superadmin_created', ['user_id' => $user->id, 'resource' => 'users', 'record_id' => $user->id, 'summary' => (string) $user->username]);

        return $user;
    }

    /**
     * Loads the demo data of the chosen modules ([] = every module with demo data) and of the
     * modules they require, in dependency order (e.g. Auth's demo users before News).
     *
     * @param list<string> $requested
     * @param list<string> $order     Modules in dependency order (modules:sync)
     */
    private function loadDemo(array $requested, array $order, string $environment, InstallResult $result): void
    {
        $manager   = new DemoDataManager(environment: $environment);
        $available = $manager->modulesWithDemo();
        $wanted    = $requested === [] ? $available : $this->withDependencies($requested, $available);

        foreach ($requested as $module) {
            if (! in_array($module, $available, true)) {
                throw new InstallException(lang('Install.error.noDemo', [$module]));
            }
        }

        try {
            foreach ($order as $module) {
                if (in_array($module, $wanted, true) && $manager->load($module)) {
                    $result->demoLoaded[] = $module;
                }
            }
        } finally {
            // Also after a failure: the modules loaded before it keep their demo users.
            $result->demoSecrets = $manager->takeSecrets();
        }
    }

    /**
     * @param list<string> $modules
     * @param list<string> $available
     *
     * @return list<string>
     */
    private function withDependencies(array $modules, array $available): array
    {
        $manifests = [];

        foreach ((new ModuleDiscovery())->discover() as $name => $module) {
            $manifests[$name] = $module->manifest;
        }

        $all       = [];
        $queue     = $modules;

        while ($queue !== []) {
            $module = array_shift($queue);

            if (in_array($module, $all, true)) {
                continue;
            }

            $all[] = $module;

            foreach (array_keys($manifests[$module]->requires ?? []) as $required) {
                if (in_array($required, $available, true)) {
                    $queue[] = $required;
                }
            }
        }

        return $all;
    }
}
