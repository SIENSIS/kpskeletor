<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Updates\Services\SignatureVerifier;
use Modules\Updates\Services\UpdateException;

/**
 * Release archives: SHA-256 and minisign (Ed25519) signature; an archive signed with a test key
 * pair verifies, and a modified archive, another key, a forged trusted comment, a bad checksum
 * or no configured key are refused.
 *
 * @internal
 */
final class SignatureVerifierTest extends CIUnitTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = (string) tempnam(sys_get_temp_dir(), 'kpsig');
        file_put_contents($this->file, random_bytes(3000) . 'release');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testASignedArchiveVerifiesInBothMinisignModes(): void
    {
        $signer   = new TestSigner();
        $verifier = new SignatureVerifier(["untrusted comment: minisign public key\n" . $signer->publicKey() . "\n"]);
        $contents = (string) file_get_contents($this->file);
        $checksum = hash('sha256', $contents) . '  release.zip';

        $verifier->verifyFile($this->file, $signer->sign($contents), $checksum);
        $verifier->verifyFile($this->file, $signer->sign($contents, false), strtoupper(hash('sha256', $contents)));
        $this->assertCount(1, $verifier->keys());
    }

    public function testAModifiedArchiveIsRefused(): void
    {
        $signer    = new TestSigner();
        $contents  = (string) file_get_contents($this->file);
        $signature = $signer->sign($contents);
        file_put_contents($this->file, 'X', FILE_APPEND);

        $this->assertRefused(lang('Updates.error.signature'), fn () => (new SignatureVerifier([$signer->publicKey()]))->verifySignature($this->file, $signature));
        $this->assertRefused(lang('Updates.error.checksum'), fn () => (new SignatureVerifier([$signer->publicKey()]))->verifyFile($this->file, $signature, hash('sha256', $contents)));
    }

    public function testOtherKeysForgedCommentsAndMissingKeysAreRefused(): void
    {
        $signer    = new TestSigner();
        $other     = new TestSigner();
        $contents  = (string) file_get_contents($this->file);
        $signature = $signer->sign($contents);
        $checksum  = hash('sha256', $contents);

        // Signed by a key that is not trusted.
        $this->assertRefused(null, fn () => (new SignatureVerifier([$other->publicKey()]))->verifyFile($this->file, $signature, $checksum));
        // Same key id, another key (a key id is not a proof).
        $impostor = base64_encode('Ed' . $signer->keyId . substr((string) base64_decode($other->publicKey(), true), 10));
        $this->assertRefused(lang('Updates.error.signature'), fn () => (new SignatureVerifier([$impostor]))->verifyFile($this->file, $signature, $checksum));
        // The trusted comment changed after signing.
        $lines    = explode("\n", $signature);
        $lines[2] = 'trusted comment: timestamp:1999999999	file:other.zip';
        $this->assertRefused(lang('Updates.error.signature'), fn () => (new SignatureVerifier([$signer->publicKey()]))->verifyFile($this->file, implode("\n", $lines), $checksum));
        // Garbage, no key, no checksum.
        $this->assertRefused(lang('Updates.error.signatureFormat'), fn () => (new SignatureVerifier([$signer->publicKey()]))->verifyFile($this->file, "untrusted comment: x\nAAAA\n", $checksum));
        $this->assertRefused(lang('Updates.error.noKey'), fn () => (new SignatureVerifier([]))->verifyFile($this->file, $signature, $checksum));
        $this->assertRefused(lang('Updates.error.noKey'), fn () => (new SignatureVerifier(['not a key']))->verifyFile($this->file, $signature, $checksum));
        $this->assertRefused(lang('Updates.error.checksumFormat'), fn () => (new SignatureVerifier([$signer->publicKey()]))->verifyFile($this->file, $signature, 'abc'));
    }

    /**
     * @param \Closure(): void $call
     */
    private function assertRefused(?string $message, \Closure $call): void
    {
        try {
            $call();
            $this->fail('Should be refused.');
        } catch (UpdateException $e) {
            if ($message !== null) {
                $this->assertSame($message, $e->getMessage());
            }
        }
    }
}
