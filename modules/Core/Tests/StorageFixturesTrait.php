<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Config\Services;
use Config\Storage as StorageConfig;
use Modules\Core\Storage\Storage;
use Modules\Core\Storage\UploadValidator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Storage disks in a temporary folder of writable/tests, a validator with known global rules and
 * small generated files (PNG, JPEG with EXIF and a trailing payload, PDF).
 */
trait StorageFixturesTrait
{
    private string $storageRoot = '';

    protected function storageConfig(): StorageConfig
    {
        $this->storageRoot = WRITEPATH . 'tests/storage-' . bin2hex(random_bytes(4));

        $config        = new StorageConfig();
        $config->disks = [
            'public'  => ['root' => $this->storageRoot . '/public', 'url' => 'uploads', 'dirPermission' => 0755, 'filePermission' => 0644],
            'private' => ['root' => $this->storageRoot . '/private', 'url' => null, 'dirPermission' => 0750, 'filePermission' => 0640],
        ];

        return $config;
    }

    /**
     * @param list<string> $types
     */
    protected function validator(StorageConfig $config, array $types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'svg', 'docx'], int $maxBytes = 1048576, bool $allowSvg = false): UploadValidator
    {
        return new UploadValidator($config, $types, $maxBytes, $allowSvg);
    }

    protected function makeStorage(bool $allowSvg = false, bool $inject = true): Storage
    {
        $config  = $this->storageConfig();
        $storage = new Storage($config, $this->validator($config, allowSvg: $allowSvg));

        if ($inject) {
            Services::injectMock('storage', $storage);
        }

        return $storage;
    }

    protected function removeStorageRoot(): void
    {
        Services::resetSingle('storage');

        if ($this->storageRoot === '' || ! is_dir($this->storageRoot)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->storageRoot, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($this->storageRoot);
    }

    protected function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kpt');
        file_put_contents($path, $contents);

        return $path;
    }

    protected static function png(int $width = 8, int $height = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A JPEG with an EXIF segment holding a "GPS" marker and bytes appended after the image.
     */
    protected static function jpegWithMetadata(): string
    {
        $image = imagecreatetruecolor(10, 10);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 200));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $payload = "Exif\0\0SECRET-GPS-41.3851N-2.1734E";
        $app1    = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2) . 'APPENDED-PAYLOAD';
    }

    protected static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }
}
