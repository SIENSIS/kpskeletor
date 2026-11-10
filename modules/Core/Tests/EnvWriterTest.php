<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\DotEnv;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Install\EnvWriter;
use Modules\Core\Install\InstallException;
use Modules\Core\Install\KeyGenerator;

/**
 * `.env` written by the installer: values replace the template's lines and read back unchanged
 * through CodeIgniter's DotEnv, whatever characters a password has.
 *
 * @internal
 */
final class EnvWriterTest extends CIUnitTestCase
{
    use InstallFixturesTrait;

    protected function tearDown(): void
    {
        $this->restoreInstallConfig();
        parent::tearDown();
    }

    /**
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    private function roundTrip(array $values): array
    {
        $dir    = $this->installRoot();
        $writer = new EnvWriter();
        $writer->write($dir . '/.env', $writer->render($values, 'Test'));

        $parsed = (new DotEnv($dir))->parse();
        $this->assertIsArray($parsed);

        return $parsed;
    }

    public function testValuesReadBackUnchanged(): void
    {
        $password = "p'a\"ss w\\ord #1 \$x é";
        $parsed   = $this->roundTrip([
            'CI_ENVIRONMENT'             => 'production',
            'database.default.password'  => $password,
            'database.default.DBDriver'  => 'MySQLi',
            'database.default.DBPrefix'  => '',
            'some.newKey'                => 'value with spaces',
        ]);

        $this->assertSame('production', $parsed['CI_ENVIRONMENT']);
        $this->assertSame($password, $parsed['database.default.password']);
        $this->assertSame('MySQLi', $parsed['database.default.DBDriver']);
        $this->assertSame('', $parsed['database.default.DBPrefix']);
        $this->assertSame('value with spaces', $parsed['some.newKey']);
    }

    public function testTemplateLinesAreReplacedNotDuplicated(): void
    {
        $content = (new EnvWriter())->render(['CI_ENVIRONMENT' => 'development', 'app.maskKey' => str_repeat('a', 64)]);

        $this->assertSame(1, preg_match_all('/^CI_ENVIRONMENT\s*=/m', $content));
        $this->assertStringContainsString("CI_ENVIRONMENT = development\n", $content);
        $this->assertSame(1, preg_match_all('/^#?\s*app\.maskKey\s*=/m', $content));
        $this->assertStringNotContainsString('INSTALLER', $content);
    }

    public function testRefusesValuesDotEnvWouldChange(): void
    {
        $this->expectException(InstallException::class);
        (new EnvWriter())->render(['database.default.password' => 'a${HOME}b']);
    }

    public function testRefusesLineBreaks(): void
    {
        $this->assertFalse(EnvWriter::acceptable("a\nb"));
        $this->assertTrue(EnvWriter::acceptable('a$b'));
    }

    public function testWrittenFileIsNotWorldReadable(): void
    {
        $path = $this->installRoot() . '/.env';
        (new EnvWriter())->write($path, "A = 1\n");

        $this->assertSame(0640, fileperms($path) & 0777);
        $this->assertSame([], glob($this->installRoot() . '/.env.*.tmp'));
    }

    public function testCompleteAddsOnlyMissingOrEmptyKeys(): void
    {
        $path = $this->installRoot() . '/.env';
        file_put_contents($path, "CI_ENVIRONMENT = development\napp.maskKey =\nencryption.key = hex2bin:00ff\n");

        $added = (new EnvWriter())->complete($path, ['app.maskKey' => 'new-mask', 'encryption.key' => 'other', 'backup.password' => 'x']);

        $this->assertSame(['app.maskKey', 'backup.password'], $added);
        $parsed = (new DotEnv($this->installRoot()))->parse();
        $this->assertSame('new-mask', $parsed['app.maskKey']);
        $this->assertSame('hex2bin:00ff', $parsed['encryption.key']);
    }

    public function testGeneratedKeys(): void
    {
        $keys = new KeyGenerator();

        $this->assertMatchesRegularExpression('/^hex2bin:[0-9a-f]{64}$/', $keys->encryptionKey());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $keys->maskKey());
        $this->assertNotSame($keys->maskKey(), $keys->maskKey());
    }
}
