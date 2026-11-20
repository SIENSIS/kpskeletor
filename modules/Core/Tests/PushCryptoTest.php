<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Push\Base64Url;
use Modules\Core\Push\EcKey;
use Modules\Core\Push\PayloadEncryption;
use Modules\Core\Push\Vapid;

/**
 * Web Push cryptography in the Core with OpenSSL only (D-44-01): the RFC 8291 appendix A test
 * vectors, a round trip decrypted with the receiver's keys, and VAPID (RFC 8292) tokens verified
 * with the public key, including the example token of RFC 8292 section 2.4.
 *
 * @internal
 */
final class PushCryptoTest extends CIUnitTestCase
{
    // RFC 8291, appendix A.
    private const PLAINTEXT   = 'When I grow up, I want to be a watermelon';
    private const AS_PUBLIC   = 'BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8';
    private const AS_PRIVATE  = 'yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw';
    private const UA_PUBLIC   = 'BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4';
    private const UA_PRIVATE  = 'q1dXpw3UpT5VOmu_cf_v6ih07Aems3njxI-JWgLcM94';
    private const SALT        = 'DGv6ra1nlYgDCS1FRnbzlw';
    private const AUTH_SECRET = 'BTBZMqHH6r4Tts7J_aSIgg';
    private const ECDH_SECRET = 'kyrL1jIIOHEzg3sM2ZWRHDRB62YACZhhSlknJ672kSs';
    private const IKM         = 'S4lYMb_L0FxCeq0WhDx813KgSYqU26kOyzWUdsXYyrg';
    private const CEK         = 'oIhVW04MRdy2XN9CiKLxTg';
    private const NONCE       = '4h_95klXJ5E_qnoN';
    private const BODY        = 'DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A_yl95bQpu6cVPTpK4Mqgkf1CXztLVBSt2Ks3oZwbuwXPXLWyouBWLVWGNWQexSgSxsj_Qulcy4a-fN';

    private static function raw(string $base64url): string
    {
        $bytes = Base64Url::decode($base64url);
        self::assertNotNull($bytes);

        return $bytes;
    }

    public function testTheIntermediateValuesOfRfc8291AppendixA(): void
    {
        $ecdh = EcKey::sharedSecret(EcKey::privateKey(self::raw(self::AS_PRIVATE), self::raw(self::AS_PUBLIC)), EcKey::publicKey(self::raw(self::UA_PUBLIC)));
        $this->assertSame(self::ECDH_SECRET, Base64Url::encode($ecdh));

        // The receiver computes the same secret from its side.
        $this->assertSame($ecdh, EcKey::sharedSecret(EcKey::privateKey(self::raw(self::UA_PRIVATE), self::raw(self::UA_PUBLIC)), EcKey::publicKey(self::raw(self::AS_PUBLIC))));

        $ikm = PayloadEncryption::hkdf(self::raw(self::AUTH_SECRET), $ecdh, "WebPush: info\x00" . self::raw(self::UA_PUBLIC) . self::raw(self::AS_PUBLIC), 32);
        $this->assertSame(self::IKM, Base64Url::encode($ikm));
        $this->assertSame(self::CEK, Base64Url::encode(PayloadEncryption::hkdf(self::raw(self::SALT), $ikm, "Content-Encoding: aes128gcm\x00", 16)));
        $this->assertSame(self::NONCE, Base64Url::encode(PayloadEncryption::hkdf(self::raw(self::SALT), $ikm, "Content-Encoding: nonce\x00", 12)));
    }

    public function testTheEncryptedBodyOfRfc8291AppendixA(): void
    {
        $body = PayloadEncryption::encrypt(
            self::PLAINTEXT,
            self::raw(self::UA_PUBLIC),
            self::raw(self::AUTH_SECRET),
            ['public' => self::raw(self::AS_PUBLIC), 'private' => self::raw(self::AS_PRIVATE)],
            self::raw(self::SALT),
        );

        $this->assertSame(self::BODY, Base64Url::encode($body));
        $this->assertSame(self::PLAINTEXT, PayloadEncryption::decrypt($body, self::raw(self::UA_PUBLIC), self::raw(self::UA_PRIVATE), self::raw(self::AUTH_SECRET)));
    }

    public function testARoundTripWithNewKeysDecryptsWithTheReceiversKeys(): void
    {
        $receiver = EcKey::generate();
        $auth     = random_bytes(16);
        $payload  = json_encode(['title' => 'Nova notícia', 'body' => 'Àrea privada — ñ', 'url' => 'https://example.com/backend/x'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $first  = PayloadEncryption::encrypt($payload, $receiver['public'], $auth);
        $second = PayloadEncryption::encrypt($payload, $receiver['public'], $auth);

        $this->assertNotSame($first, $second, 'A new salt and key pair per message.');
        $this->assertSame(PayloadEncryption::RECORD_SIZE, unpack('N', substr($first, 16, 4))[1]);
        $this->assertSame(65, ord($first[20]));
        $this->assertSame($payload, PayloadEncryption::decrypt($first, $receiver['public'], $receiver['private'], $auth));
        $this->assertSame($payload, PayloadEncryption::decrypt($second, $receiver['public'], $receiver['private'], $auth));

        $this->expectException(\RuntimeException::class);
        PayloadEncryption::decrypt($first, $receiver['public'], $receiver['private'], random_bytes(16));
    }

    public function testTooLongPayloadsAndInvalidKeysAreRefused(): void
    {
        $receiver = EcKey::generate();

        try {
            PayloadEncryption::encrypt(str_repeat('x', PayloadEncryption::MAX_PAYLOAD + 1), $receiver['public'], random_bytes(16));
            $this->fail('Too long.');
        } catch (\RuntimeException) {
        }

        $this->assertFalse(EcKey::isPublicKey(str_repeat("\x04", 65)), 'Not a point of the curve.');
        $this->assertFalse(EcKey::isPublicKey(substr($receiver['public'], 1)));
        $this->assertTrue(EcKey::isPublicKey($receiver['public']));

        $this->expectException(\RuntimeException::class);
        PayloadEncryption::encrypt('x', $receiver['public'], random_bytes(15));
    }

    public function testTheExampleTokenOfRfc8292Verifies(): void
    {
        // RFC 8292 section 2.4 (and the `k` of section 3.2).
        $jwt = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJodHRwczovL3B1c2guZXhhbXBsZS5uZXQiLCJleHAiOjE0NTM1MjM3NjgsInN1YiI6Im1haWx0bzpwdXNoQGV4YW1wbGUuY29tIn0.i3CYb7t4xfxCDquptFOepC9GAu_HLGkMlMuCGSK2rpiUfnK9ojFwDXb1JrErtmysazNjjvW2L9OkSSHzvoD1oA';
        $key = self::raw('BA1Hxzyi1RUM1b5wjxsn7nGxAszw2u61m164i3MrAIxHF6YK5h4SDYic-dRuU_RCPCfA5aq9ojSwk5Y2EmClBPs');

        $this->assertTrue(Vapid::verify($jwt, $key));
        $this->assertFalse(Vapid::verify(str_replace('.i3C', '.i3D', $jwt), $key), 'A changed signature fails.');
        $this->assertFalse(Vapid::verify($jwt, EcKey::generate()['public']), 'Another key fails.');
    }

    public function testTheVapidHeaderOfThisSite(): void
    {
        $keys     = EcKey::generate();
        $now      = 1_800_000_000;
        $header   = (new Vapid($keys['public'], $keys['private'], 'mailto:webmaster@example.com'))->authorization('https://fcm.googleapis.com/fcm/send/abc:def?x=1', $now);

        $this->assertMatchesRegularExpression('/^vapid t=([A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+), k=([A-Za-z0-9_-]+)$/', $header);
        preg_match('/^vapid t=([^,]+), k=(.+)$/', $header, $match);
        $this->assertSame(Base64Url::encode($keys['public']), $match[2]);
        $this->assertTrue(Vapid::verify($match[1], $keys['public']));

        [$head, $claims] = array_map(static fn (string $part): array => json_decode((string) Base64Url::decode($part), true), array_slice(explode('.', $match[1]), 0, 2));
        $this->assertSame(['typ' => 'JWT', 'alg' => 'ES256'], $head);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud'], 'The origin of the endpoint.');
        $this->assertSame('mailto:webmaster@example.com', $claims['sub']);
        $this->assertGreaterThan($now, $claims['exp']);
        $this->assertLessThanOrEqual($now + Vapid::MAX_LIFETIME, $claims['exp'], 'At most 24 hours.');
    }

    public function testSignatureConversionsKeepLeadingZeros(): void
    {
        $raw = "\x00" . str_repeat("\x01", 31) . "\x80" . str_repeat("\x02", 31);

        $this->assertSame($raw, Vapid::derToRaw(Vapid::rawToDer($raw)));
        $this->assertNull(Base64Url::decode(''));
        $this->assertNull(Base64Url::decode('not base64!'));
        $this->assertSame("\xfb\xff", Base64Url::decode('-_8'));
    }
}
