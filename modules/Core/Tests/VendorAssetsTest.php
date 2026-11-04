<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Vendored libraries (public/assets/vendor) match vendor.json: version, licence, source and a
 * SHA-256 per file, nothing missing and nothing extra.
 *
 * @internal
 */
final class VendorAssetsTest extends CIUnitTestCase
{
    public function testChecksumsMatch(): void
    {
        $root     = FCPATH . 'assets/vendor';
        $manifest = json_decode((string) file_get_contents($root . '/vendor.json'), true);

        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('bootstrap', $manifest);
        $this->assertArrayHasKey('bootstrap-icons', $manifest);

        foreach ($manifest as $library => $entry) {
            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $entry['version'], $library);
            $this->assertNotEmpty($entry['licence'], $library);
            $this->assertStringStartsWith('https://', $entry['source'], $library);

            $files = [];
            $it    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $library, \FilesystemIterator::SKIP_DOTS));

            foreach ($it as $file) {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/' . $library) + 1));
            }

            sort($files);
            $this->assertSame(array_keys($entry['files']), $files, $library);

            foreach ($entry['files'] as $path => $hash) {
                $this->assertSame($hash, hash_file('sha256', $root . '/' . $library . '/' . $path), "{$library}/{$path}");
            }
        }
    }
}
