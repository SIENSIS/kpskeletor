<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A fake project for the asset pipeline under writable/ (CodeIgniter's Publisher only writes
 * inside ROOTPATH): modules/, themes/ and public/ folders, removed afterwards.
 */
trait AssetFixturesTrait
{
    private string $assetRoot = '';

    private function assetRoot(): string
    {
        if ($this->assetRoot === '') {
            $this->assetRoot = WRITEPATH . 'tests/assets-' . bin2hex(random_bytes(6));
            mkdir($this->assetRoot . '/public', 0777, true);
            mkdir($this->assetRoot . '/modules', 0777, true);
            mkdir($this->assetRoot . '/themes', 0777, true);
        }

        return $this->assetRoot;
    }

    /**
     * @param array<string, string> $files path inside Assets/ => content
     */
    private function assetModule(string $name, array $files): void
    {
        $this->writeFiles($this->assetRoot() . '/modules/' . $name, ['Config/Module.php' => '<?php // fixture'] + $this->prefix('Assets/', $files));
    }

    /**
     * @param array<string, string> $files path inside Assets/ => content
     */
    private function assetTheme(string $slug, array $files): void
    {
        $this->writeFiles($this->assetRoot() . '/themes/' . $slug, ['Theme.php' => '<?php // fixture'] + $this->prefix('Assets/', $files));
    }

    /**
     * @param array<string, string> $files
     *
     * @return array<string, string>
     */
    private function prefix(string $prefix, array $files): array
    {
        $result = [];

        foreach ($files as $path => $content) {
            $result[$prefix . $path] = $content;
        }

        return $result;
    }

    /**
     * @param array<string, string> $files
     */
    private function writeFiles(string $folder, array $files): void
    {
        foreach ($files as $path => $content) {
            $file = $folder . '/' . $path;

            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            file_put_contents($file, $content);
        }
    }

    private function removeAssetRoot(): void
    {
        if ($this->assetRoot === '' || ! is_dir($this->assetRoot)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->assetRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->assetRoot);
        $this->assetRoot = '';
    }
}
