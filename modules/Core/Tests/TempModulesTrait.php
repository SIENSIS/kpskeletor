<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

/**
 * A temporary folder of modules for tests, removed afterwards. Module names are unique per run
 * so that their classes never clash with modules loaded by another test.
 */
trait TempModulesTrait
{
    private string $tempRoot = '';

    private function tempRoot(): string
    {
        if ($this->tempRoot === '') {
            $this->tempRoot = sys_get_temp_dir() . '/kps-modules-' . bin2hex(random_bytes(6));
            mkdir($this->tempRoot, 0777, true);
        }

        return $this->tempRoot;
    }

    private function uniqueModuleName(): string
    {
        return 'Fx' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function removeTempRoot(): void
    {
        if ($this->tempRoot !== '' && is_dir($this->tempRoot)) {
            helper('filesystem');
            delete_files($this->tempRoot, true, false, true);
            rmdir($this->tempRoot);
        }

        $this->tempRoot = '';
    }
}
