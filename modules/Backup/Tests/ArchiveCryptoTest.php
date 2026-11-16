<?php

declare(strict_types=1);

namespace Modules\Backup\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backup\Config\Backup as BackupConfig;
use Modules\Backup\Services\ArchiveCrypto;
use Modules\Backup\Services\BackupException;
use Modules\Backup\Services\BackupService;

/**
 * The encrypted file format (D-00-46): round trip over several chunks, and every way a file can
 * be wrong — password, a changed byte, a changed header, truncated, extra bytes, a crafted
 * key-derivation cost — is refused and leaves no decrypted output behind.
 *
 * @internal
 */
final class ArchiveCryptoTest extends CIUnitTestCase
{
    private const PASSWORD = 'correct-horse-battery';

    private string $root = '';

    private ArchiveCrypto $crypto;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/kpcrypto-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
        $config           = new BackupConfig();
        $config->memLimit = 8 * 1024 * 1024;
        $this->crypto     = new ArchiveCrypto($config);
    }

    protected function tearDown(): void
    {
        BackupService::removeFolder($this->root);
        parent::tearDown();
    }

    public function testRoundTripOverSeveralChunksAndAnEmptyFile(): void
    {
        foreach (['big' => random_bytes(ArchiveCrypto::CHUNK * 2 + 12345), 'exact' => str_repeat('x', ArchiveCrypto::CHUNK), 'empty' => ''] as $name => $plain) {
            file_put_contents("{$this->root}/{$name}", $plain);
            $this->crypto->encryptFile("{$this->root}/{$name}", "{$this->root}/{$name}.enc", self::PASSWORD);

            $this->assertTrue($this->crypto->isBackup("{$this->root}/{$name}.enc"));
            $this->assertStringNotContainsString(substr($plain, 0, 64) ?: 'nothing', (string) file_get_contents("{$this->root}/{$name}.enc"));
            $this->assertSame(0640, fileperms("{$this->root}/{$name}.enc") & 0777, 'Backup files are 0640.');

            $this->crypto->decryptFile("{$this->root}/{$name}.enc", "{$this->root}/{$name}.out", self::PASSWORD);
            $this->assertSame($plain, file_get_contents("{$this->root}/{$name}.out"), $name);
            $this->assertTrue($this->crypto->checkPassword("{$this->root}/{$name}.enc", self::PASSWORD));
            $this->assertFalse($this->crypto->checkPassword("{$this->root}/{$name}.enc", 'another-password-1'));
        }
    }

    public function testWrongPasswordIsRefused(): void
    {
        $file = $this->encrypted(random_bytes(5000));

        $this->assertRefused($file, lang('Backup.error.password'), 'wrong-password-123');
    }

    public function testAChangedByteIsRefused(): void
    {
        $file = $this->encrypted(random_bytes(ArchiveCrypto::CHUNK + 5000));
        $data = (string) file_get_contents($file);
        $at   = ArchiveCrypto::HEADER_BYTES + ArchiveCrypto::CHUNK + 100; // inside the second chunk
        $data[$at] = chr(ord($data[$at]) ^ 1);
        file_put_contents($file, $data);

        $this->assertRefused($file, lang('Backup.error.tampered'));
    }

    public function testAChangedHeaderIsRefused(): void
    {
        $file = $this->encrypted('secret data');
        $data = (string) file_get_contents($file);
        $data[30] = chr(ord($data[30]) ^ 1); // inside the salt
        file_put_contents($file, $data);

        $this->expectException(BackupException::class);
        $this->crypto->decryptFile($file, $this->root . '/out', self::PASSWORD);
    }

    public function testATruncatedFileOrExtraBytesAreRefused(): void
    {
        $file = $this->encrypted(random_bytes(ArchiveCrypto::CHUNK + 10));
        $data = (string) file_get_contents($file);

        // Without its last chunk the stream has no FINAL tag.
        $firstChunk = ArchiveCrypto::HEADER_BYTES + 4 + ArchiveCrypto::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
        file_put_contents($file, substr($data, 0, $firstChunk));
        $this->assertRefused($file, lang('Backup.error.tampered'));

        file_put_contents($file, substr($data, 0, -5));
        $this->assertRefused($file, lang('Backup.error.tampered'));

        file_put_contents($file, $data . 'extra');
        $this->assertRefused($file, lang('Backup.error.tampered'));
    }

    public function testFilesThatAreNotBackupsOrHaveACraftedCostAreRefused(): void
    {
        file_put_contents($this->root . '/plain.zip', 'PK' . random_bytes(100));
        $this->assertFalse($this->crypto->isBackup($this->root . '/plain.zip'));
        $this->assertRefused($this->root . '/plain.zip', lang('Backup.error.notBackup'));

        // A header asking for 64 GiB of memory to derive the key (denial of service).
        $file = $this->encrypted('data');
        $data = (string) file_get_contents($file);
        $data = substr($data, 0, 14) . pack('J', 64 * 1024 ** 3) . substr($data, 22);
        file_put_contents($file, $data);
        $this->assertFalse($this->crypto->isBackup($file));
        $this->assertRefused($file, lang('Backup.error.format'));
    }

    private function encrypted(string $plain): string
    {
        $source = $this->root . '/plain-' . bin2hex(random_bytes(3));
        file_put_contents($source, $plain);
        $this->crypto->encryptFile($source, $source . '.enc', self::PASSWORD);

        return $source . '.enc';
    }

    private function assertRefused(string $file, string $message, string $password = self::PASSWORD): void
    {
        $out = $this->root . '/out-' . bin2hex(random_bytes(3));

        try {
            $this->crypto->decryptFile($file, $out, $password);
            $this->fail('The file was accepted.');
        } catch (BackupException $e) {
            $this->assertSame($message, $e->getMessage());
        }

        $this->assertFileDoesNotExist($out, 'Nothing decrypted is left behind.');
    }
}
