<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Packages\PackageRefused;
use Modules\Core\Packages\SignatureCheck;
use Modules\Core\Security\Minisign;
use Modules\Core\Security\MinisignRefused;
use Tests\Support\Security\TestSigner;

/**
 * The one minisign verifier (releases and packages): both minisign modes verify; a modified
 * file, another key, a forged trusted comment, a malformed signature, no key or an unreadable
 * file are refused with their reason; `SignatureCheck` turns reasons into package messages.
 *
 * @internal
 */
final class MinisignTest extends CIUnitTestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = (string) tempnam(sys_get_temp_dir(), 'kpmini');
        file_put_contents($this->file, random_bytes(2000) . 'package');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testASignedFileVerifiesInBothModes(): void
    {
        $signer   = new TestSigner();
        $minisign = new Minisign(["untrusted comment: minisign public key\n" . $signer->publicKey() . "\n"]);
        $contents = (string) file_get_contents($this->file);

        $minisign->verify($this->file, $signer->sign($contents));
        $minisign->verify($this->file, $signer->sign($contents, false));
        $this->assertCount(1, $minisign->keys());
    }

    public function testRefusalsCarryTheirReason(): void
    {
        $signer    = new TestSigner();
        $other     = new TestSigner();
        $contents  = (string) file_get_contents($this->file);
        $signature = $signer->sign($contents);
        $minisign  = new Minisign([$signer->publicKey()]);

        $this->assertReason(Minisign::NO_KEY, fn () => (new Minisign([]))->verify($this->file, $signature));
        $this->assertReason(Minisign::NO_KEY, fn () => (new Minisign(['not a key']))->verify($this->file, $signature));
        $this->assertReason(Minisign::FORMAT, fn () => $minisign->verify($this->file, "untrusted comment: x\nAAAA\n"));
        $this->assertReason(Minisign::UNKNOWN_KEY, fn () => (new Minisign([$other->publicKey()]))->verify($this->file, $signature));

        $lines    = explode("\n", $signature);
        $lines[2] = 'trusted comment: forged';
        $this->assertReason(Minisign::SIGNATURE, fn () => $minisign->verify($this->file, implode("\n", $lines)));

        file_put_contents($this->file, $contents . 'x');
        $this->assertReason(Minisign::SIGNATURE, fn () => $minisign->verify($this->file, $signature));

        unlink($this->file);
        $this->assertReason(Minisign::UNREADABLE, fn () => $minisign->verify($this->file, $signature));
    }

    public function testUnknownKeysAreNamedByTheirMinisignId(): void
    {
        $signer = new TestSigner();

        try {
            (new Minisign([(new TestSigner())->publicKey()]))->verify($this->file, $signer->sign((string) file_get_contents($this->file)));
            $this->fail('Refusal expected');
        } catch (MinisignRefused $e) {
            $this->assertSame(strtoupper(bin2hex(strrev($signer->keyId))), $e->keyId);
        }
    }

    public function testPackageSignaturesUseThePackageMessages(): void
    {
        $signer    = new TestSigner();
        $signature = $signer->sign((string) file_get_contents($this->file));

        (new SignatureCheck([$signer->publicKey()]))->verify($this->file, $signature);

        $cases = [
            lang('Core.packages.noPublisherKey')  => fn () => (new SignatureCheck([]))->verify($this->file, $signature),
            lang('Core.packages.signatureFormat') => fn () => (new SignatureCheck([$signer->publicKey()]))->verify($this->file, 'x'),
        ];

        foreach ($cases as $message => $call) {
            try {
                $call();
                $this->fail('Refusal expected: ' . $message);
            } catch (PackageRefused $e) {
                $this->assertSame($message, $e->getMessage());
            }
        }

        file_put_contents($this->file, 'changed');
        $this->expectException(PackageRefused::class);
        $this->expectExceptionMessage(lang('Core.packages.badSignature'));
        (new SignatureCheck([$signer->publicKey()]))->verify($this->file, $signature);
    }

    private function assertReason(string $reason, callable $call): void
    {
        try {
            $call();
            $this->fail('Refusal expected: ' . $reason);
        } catch (MinisignRefused $e) {
            $this->assertSame($reason, $e->reason);
        }
    }
}
