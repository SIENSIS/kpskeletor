<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Archive\ArchiveRefused;
use Modules\Core\Archive\SafeZip;
use ZipArchive;

/**
 * Archives from outside (backups, module packages): zip-slip names, links, entries outside the
 * allowed folders and zip bombs are refused before anything is extracted; extraction writes only
 * inside the target, with the given permissions, and skips the given extensions.
 *
 * @internal
 */
final class SafeZipTest extends CIUnitTestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/kpsafezip-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
        parent::tearDown();
    }

    public function testDangerousNamesAreRefused(): void
    {
        foreach (['files/../../evil.php', '/etc/passwd', 'files\\evil.php', 'C:/evil.txt', 'files//a.txt', 'other/a.txt', "files/a\x01.txt"] as $name) {
            $zip = $this->zip([$name => 'x']);

            try {
                SafeZip::open($zip, ['manifest.json', 'files/'], 1_000_000);
                $this->fail("Accepted: {$name}");
            } catch (ArchiveRefused) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testSymbolicLinksAreRefused(): void
    {
        $path = $this->root . '/link.zip';
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('files/link', '/etc/passwd');
        $zip->setExternalAttributesName('files/link', ZipArchive::OPSYS_UNIX, (0120777 << 16));
        $zip->close();

        $this->expectException(ArchiveRefused::class);
        SafeZip::open($path, ['files/'], 1_000_000);
    }

    public function testZipBombsAreRefused(): void
    {
        // 50 MB of zeros compress to a few KB: the ratio is refused.
        $bomb = $this->zip(['files/zeros' => str_repeat("\0", 50_000_000)]);

        try {
            SafeZip::open($bomb, ['files/'], PHP_INT_MAX);
            $this->fail('Ratio accepted.');
        } catch (ArchiveRefused $e) {
            $this->assertSame(lang('Core.archive.ratio', ['files/zeros']), $e->getMessage());
        }

        // The total uncompressed size and the number of entries.
        $big = $this->zip(['files/a' => random_bytes(3000), 'files/b' => random_bytes(3000)]);

        try {
            SafeZip::open($big, ['files/'], 5000);
            $this->fail('Size accepted.');
        } catch (ArchiveRefused $e) {
            $this->assertSame(lang('Core.archive.tooLarge'), $e->getMessage());
        }

        $this->expectException(ArchiveRefused::class);
        SafeZip::open($big, ['files/'], 1_000_000, 1);
    }

    public function testExtractionStaysInsideTheTargetWithItsPermissions(): void
    {
        $zip  = $this->zip(['manifest.json' => '{}', 'files/a/b.txt' => 'hello', 'files/c.php' => '<?php', 'files/.htaccess' => 'Allow']);
        $safe = SafeZip::open($zip, ['manifest.json', 'files/'], 1_000_000);

        $this->assertSame(['manifest.json' => 2, 'files/a/b.txt' => 5, 'files/c.php' => 5, 'files/.htaccess' => 5], $safe->entries());
        $this->assertSame('{}', $safe->read('manifest.json'));
        $this->assertSame(hash('sha256', 'hello'), $safe->hash('files/a/b.txt'));

        $count = $safe->extract('files/', $this->root . '/out', 0640, 0750, ['php', '.htaccess']);
        $safe->close();

        $this->assertSame(1, $count);
        $this->assertSame('hello', file_get_contents($this->root . '/out/a/b.txt'));
        $this->assertSame(0640, fileperms($this->root . '/out/a/b.txt') & 0777);
        $this->assertFileDoesNotExist($this->root . '/out/c.php');
        $this->assertFileDoesNotExist($this->root . '/out/.htaccess');
    }

    public function testNotAnArchive(): void
    {
        file_put_contents($this->root . '/x.zip', 'not a zip');

        $this->expectException(ArchiveRefused::class);
        SafeZip::open($this->root . '/x.zip', ['files/'], 1000);
    }

    /**
     * @param array<string, string> $entries
     */
    private function zip(array $entries): string
    {
        $path = $this->root . '/' . bin2hex(random_bytes(4)) . '.zip';
        $zip  = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }
}
