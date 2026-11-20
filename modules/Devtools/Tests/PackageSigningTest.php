<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Config\Packages;
use Modules\Core\Packages\PackageValidator;
use Modules\Core\Security\Minisign;
use Modules\Devtools\Packages\MinisignSecretKey;
use Modules\Devtools\Packages\PackageSigner;
use Modules\Devtools\Packages\SigningFailed;

/**
 * minisign-compatible signing (D-43-01): the signatures of the minisign 0.11 CLI are reproduced
 * byte for byte (Fixtures/minisign), keys made in the test (encrypted with scrypt or not) sign
 * packages that the Core's `Minisign` and the installer's validator accept, a wrong password and
 * malformed keys are refused, and the key and the password are wiped.
 *
 * @internal
 */
final class PackageSigningTest extends CIUnitTestCase
{
    private const FIXTURES = __DIR__ . '/Fixtures/minisign/';

    private string $work = '';

    protected function setUp(): void
    {
        parent::setUp();
        service('language')->setLocale('en');
        $this->work = sys_get_temp_dir() . '/kps-sign-' . bin2hex(random_bytes(6));
        mkdir($this->work, 0777, true);
    }

    protected function tearDown(): void
    {
        PackageValidator::removeFolder($this->work);
        parent::tearDown();
    }

    public function testTheMinisignCliSignatureIsReproducedForAnUnencryptedKey(): void
    {
        $key = MinisignSecretKey::parse((string) file_get_contents(self::FIXTURES . 'plain.key'), '');

        $this->assertSame((string) file_get_contents(self::FIXTURES . 'data.plain.minisig'), PackageSigner::signature(self::FIXTURES . 'data.txt', $key, 'Tasks 1.0.0'));
        $this->assertSame(trim(explode("\n", (string) file_get_contents(self::FIXTURES . 'plain.pub'))[1]), $key->publicKey());
        $this->assertSame((string) file_get_contents(self::FIXTURES . 'plain.pub'), $key->publicKeyFile());
    }

    public function testAnEncryptedCliKeyIsDecryptedWithItsPassword(): void
    {
        // minisign's default KDF limits (1 GiB): about three seconds.
        $key = MinisignSecretKey::parse((string) file_get_contents(self::FIXTURES . 'enc.key'), 'test-password');

        $this->assertSame((string) file_get_contents(self::FIXTURES . 'data.enc.minisig'), PackageSigner::signature(self::FIXTURES . 'data.txt', $key, 'Tasks 1.0.0'));
        (new Minisign([(string) file_get_contents(self::FIXTURES . 'enc.pub')]))->verify(self::FIXTURES . 'data.txt', (string) file_get_contents(self::FIXTURES . 'data.enc.minisig'));
        $this->assertSame('2218518CBB5416F1', $key->keyIdHex());
    }

    public function testKeysMadeInTheTestSignPackagesTheInstallerAccepts(): void
    {
        $zip = $this->package();

        foreach (['secret password', ''] as $password) {
            [$keyText, $publicKey] = self::makeKey($password);
            $pass   = $password;
            $result = (new PackageSigner())->sign($zip, 'Demo 1.2.3', $keyText, $pass);

            $this->assertSame('', $keyText, 'The key text is wiped.');
            $this->assertSame('', $pass, 'The password is wiped.');
            $this->assertSame($publicKey, $result->publicKey);
            $signature = (string) file_get_contents($zip . '.minisig');
            $this->assertStringContainsString("trusted comment: Demo 1.2.3\n", $signature);
            (new Minisign([$publicKey]))->verify($zip, $signature);

            // The installer of a site that trusts the key accepts it, and one that does not refuses it.
            $config             = new Packages();
            $config->publicKeys = [$publicKey];
            $this->assertTrue((new PackageValidator($config))->validate($zip, $signature)->signed);
            $info = json_decode((string) file_get_contents($zip . '.json'), true);
            $this->assertSame(['file', 'keyId', 'publicKey', 'sha256', 'trustedComment'], array_keys($info));
            $this->assertSame(hash_file('sha256', $zip), $info['sha256']);
        }
    }

    public function testWrongPasswordsAndBadKeysAreRefused(): void
    {
        $zip = $this->package();
        [$keyText] = self::makeKey('right');

        $this->assertSignRefused('password', $zip, $keyText, 'wrong');
        $this->assertSignRefused('passwordRequired', $zip, $keyText, '');
        $this->assertSignRefused('format', $zip, "untrusted comment: x\nnot base64!", '');
        $this->assertSignRefused('format', $zip, (string) file_get_contents(self::FIXTURES . 'plain.pub'), '');
        $this->assertSignRefused('package', $this->work . '/missing.zip', $keyText, 'right');
        $this->assertFileDoesNotExist($zip . '.minisig');

        // A corrupted unencrypted key (the secret key and its public half disagree).
        $raw      = (string) base64_decode(explode("\n", (string) file_get_contents(self::FIXTURES . 'plain.key'))[1], true);
        $raw[100] = chr(ord($raw[100]) ^ 1);
        $this->assertSignRefused('format', $zip, "untrusted comment: x\n" . base64_encode($raw), '');

        // Messages are translated and never carry the key.
        $this->assertSame(lang('Devtools.packages.sign.errors.password'), (new SigningFailed('password'))->userMessage());
        $this->assertSame(lang('Devtools.packages.sign.errors.failed'), (new SigningFailed('other'))->userMessage());
    }

    public function testTheSecretIsHiddenFromBacktraces(): void
    {
        [$keyText] = self::makeKey('right');

        try {
            MinisignSecretKey::parse($keyText, 'wrong');
            $this->fail('Refusal expected');
        } catch (SigningFailed $e) {
            $trace = $e->getTraceAsString() . print_r($e->getTrace(), true);
            $this->assertStringNotContainsString(substr(explode("\n", $keyText)[1], 0, 40), $trace);
            $this->assertStringNotContainsString('wrong', $trace);
            $this->assertStringNotContainsString('wrong', $e->getMessage());
        }
    }

    /**
     * A minisign secret key file (encrypted with scrypt at libsodium's interactive limits when
     * there is a password, like `minisign -G`; unencrypted like `minisign -G -W` otherwise) and
     * the public key line.
     *
     * @return array{0: string, 1: string}
     */
    public static function makeKey(string $password): array
    {
        $pair   = sodium_crypto_sign_keypair();
        $secret = sodium_crypto_sign_secretkey($pair);
        $keyId  = random_bytes(8);
        $chk    = sodium_crypto_generichash('Ed' . $keyId . $secret, '', 32);
        $data   = $keyId . $secret . $chk;
        $salt   = random_bytes(32);
        $ops    = SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_OPSLIMIT_INTERACTIVE;
        $mem    = SODIUM_CRYPTO_PWHASH_SCRYPTSALSA208SHA256_MEMLIMIT_INTERACTIVE;

        if ($password !== '') {
            $data ^= sodium_crypto_pwhash_scryptsalsa208sha256(104, $password, $salt, $ops, $mem);
        }

        $raw = 'Ed' . ($password !== '' ? 'Sc' : "\0\0") . 'B2' . $salt . pack('P', $ops) . pack('P', $mem) . $data;

        return [
            "untrusted comment: minisign encrypted secret key\n" . base64_encode($raw) . "\n",
            base64_encode('Ed' . $keyId . sodium_crypto_sign_publickey($pair)),
        ];
    }

    private function assertSignRefused(string $reason, string $zip, string $keyText, string $password): void
    {
        try {
            (new PackageSigner())->sign($zip, 'Demo 1.2.3', $keyText, $password);
            $this->fail('Signed with ' . $reason);
        } catch (SigningFailed $e) {
            $this->assertSame($reason, $e->reason);
        }
    }

    private function package(): string
    {
        $zip     = $this->work . '/Demo-1.2.3.zip';
        $archive = new \ZipArchive();
        $archive->open($zip, \ZipArchive::CREATE);
        $archive->addFromString('Demo/Config/Module.php', "<?php\n\ndeclare(strict_types=1);\n\nnamespace Modules\\Demo\\Config;\n\nfinal class Module extends \\Modules\\Core\\Config\\ModuleManifest\n{\n    public string \$name = 'Demo';\n    public string \$version = '1.2.3';\n    public array \$requires = ['Core' => '^1.0'];\n}\n");
        $archive->close();

        return $zip;
    }
}
