<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\App;
use Config\Encryption;
use Config\Services;
use Modules\Core\Install\InstallState;

/**
 * An unconfigured installation in temporary folders (project root and writable/), and the
 * configuration values the installer changes in the running process, restored afterwards.
 */
trait InstallFixturesTrait
{
    private string $installRoot = '';

    /** @var array<string, mixed> */
    private array $savedConfig = [];

    private function installRoot(): string
    {
        if ($this->installRoot === '') {
            $this->installRoot = sys_get_temp_dir() . '/kps-install-' . bin2hex(random_bytes(6));
            mkdir($this->installRoot . '/writable', 0777, true);
        }

        return $this->installRoot;
    }

    /**
     * @param bool $rootWritable False: the project root does not exist, so it is not writable
     */
    private function installState(bool $rootWritable = true): InstallState
    {
        $root = $this->installRoot() . ($rootWritable ? '' : '/missing-root');

        return new InstallState($root, $this->installRoot() . '/writable');
    }

    private function saveInstallConfig(): void
    {
        $this->savedConfig = [
            'baseURL'    => config(App::class)->baseURL,
            'maskKey'    => config(App::class)->maskKey,
            'encryption' => config(Encryption::class)->key,
            'kpcrudy'    => config('Kpcrudy')->maskKey,
            'kpapy'      => config('Kpapy')->maskKey,
        ];
    }

    private function restoreInstallConfig(): void
    {
        if ($this->savedConfig !== []) {
            config(App::class)->baseURL     = $this->savedConfig['baseURL'];
            config(App::class)->maskKey     = $this->savedConfig['maskKey'];
            config(Encryption::class)->key  = $this->savedConfig['encryption'];
            config('Kpcrudy')->maskKey      = $this->savedConfig['kpcrudy'];
            config('Kpapy')->maskKey        = $this->savedConfig['kpapy'];
            Services::resetSingle('encrypter');
        }

        foreach (array_keys($_ENV) as $key) {
            if (str_starts_with((string) $key, 'database.default.')) {
                putenv((string) $key);
                unset($_ENV[$key]);
                service('superglobals')->unsetServer((string) $key);
            }
        }

        if ($this->installRoot !== '' && is_dir($this->installRoot)) {
            helper('filesystem');
            delete_files($this->installRoot, true, false, true);
            rmdir($this->installRoot);
        }

        $this->installRoot = '';
    }
}
