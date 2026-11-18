<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Frontend\VendorChecksums;

/**
 * VendorChecksums: changed, missing and unlisted files of a vendored library, and a missing
 * vendor.json, are reported; the committed public/assets/vendor passes.
 *
 * @internal
 */
final class VendorChecksumsTest extends CIUnitTestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = WRITEPATH . 'tmp/vendor-checksums-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/lib/js', 0o777, true);
        file_put_contents($this->root . '/lib/js/lib.js', 'console.log(1);');
        file_put_contents($this->root . '/vendor.json', (string) json_encode([
            'lib' => ['version' => '1.0.0', 'licence' => 'MIT', 'source' => 'https://example.com', 'files' => [
                'js/lib.js' => hash('sha256', 'console.log(1);'),
            ]],
        ]));
    }

    protected function tearDown(): void
    {
        helper('filesystem');
        delete_files($this->root, true);
        @rmdir($this->root);

        parent::tearDown();
    }

    public function testTheCommittedLibrariesMatch(): void
    {
        $checksums = new VendorChecksums();

        $this->assertSame([], $checksums->problems());
        $this->assertGreaterThan(0, $checksums->fileCount());
    }

    public function testAnUnchangedLibraryPasses(): void
    {
        $this->assertSame([], (new VendorChecksums($this->root))->problems());
        $this->assertSame(1, (new VendorChecksums($this->root))->fileCount());
    }

    public function testAChangedFileIsReported(): void
    {
        file_put_contents($this->root . '/lib/js/lib.js', 'alert(1);');

        $this->assertSame(['lib/js/lib.js'], (new VendorChecksums($this->root))->problems());
    }

    public function testAMissingFileIsReported(): void
    {
        unlink($this->root . '/lib/js/lib.js');

        $this->assertSame(['lib/js/lib.js'], (new VendorChecksums($this->root))->problems());
    }

    public function testAFileNotListedIsReported(): void
    {
        file_put_contents($this->root . '/lib/js/extra.js', 'alert(1);');

        $this->assertSame(['+ lib/js/extra.js'], (new VendorChecksums($this->root))->problems());
    }

    public function testAMissingOrInvalidManifestIsReported(): void
    {
        file_put_contents($this->root . '/vendor.json', '[1, 2]');
        $this->assertSame([VendorChecksums::MANIFEST], (new VendorChecksums($this->root))->problems());
        $this->assertFalse((new VendorChecksums($this->root))->hasManifest());

        unlink($this->root . '/vendor.json');
        $this->assertSame([VendorChecksums::MANIFEST], (new VendorChecksums($this->root))->problems());
    }
}
