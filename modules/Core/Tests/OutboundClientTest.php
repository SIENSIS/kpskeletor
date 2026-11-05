<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Email as EmailConfig;
use Config\Outbound;
use Modules\Core\Http\IpGuard;
use Modules\Core\Http\OutboundAllowList;
use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundException;
use Modules\Core\Http\OutboundResponse;
use Modules\Core\Http\OutboundTransport;
use Modules\Core\Mail\GuardedEmail;

/**
 * SSRF protection: HTTPS only, allow-listed hosts, no private or loopback address after DNS and
 * redirects (the checked address is the one connected to), redirect and size limits; the mail
 * service checks SMTP hosts against the same allow-list.
 *
 * @internal
 */
final class OutboundClientTest extends CIUnitTestCase
{
    /** Host => addresses of the fake DNS. */
    private const DNS = [
        'api.example.com'      => ['93.184.216.34'],
        'cdn.example.com'      => ['93.184.216.35'],
        'internal.example.com' => ['10.0.0.5'],
        'mixed.example.com'    => ['93.184.216.36', '127.0.0.1'],
        'v6.example.com'       => ['::ffff:192.168.1.1'],
        'nowhere.example.com'  => [],
        'evil.example.org'     => ['93.184.216.40'],
    ];

    private FakeTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new FakeTransport();
    }

    public function testAllowedHttpsRequestIsPinnedToTheCheckedAddress(): void
    {
        $this->transport->responses[] = new OutboundResponse(200, ['content-type' => 'application/json'], '{"ok":true}');

        $response = $this->client()->get('https://api.example.com/v1/x?q=1', ['Accept' => 'application/json']);

        $this->assertTrue($response->ok());
        $this->assertSame(['ok' => true], $response->json());
        $this->assertSame('93.184.216.34', $this->transport->sent[0]['ip']);
        $this->assertSame(443, $this->transport->sent[0]['port']);
        $this->assertSame('kpskeletor', $this->transport->sent[0]['headers']['User-Agent']);
        $this->assertSame(1024, $this->transport->sent[0]['maxBytes']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'plain HTTP' => ['http://api.example.com/', OutboundException::REASON_SCHEME];
        yield 'other scheme' => ['ftp://api.example.com/', OutboundException::REASON_SCHEME];
        yield 'host not allowed' => ['https://evil.example.org/', OutboundException::REASON_HOST];
        yield 'suffix trick' => ['https://api.example.com.evil.example.org/', OutboundException::REASON_HOST];
        yield 'private IP after DNS' => ['https://internal.example.com/', OutboundException::REASON_PRIVATE];
        yield 'one loopback address among others' => ['https://mixed.example.com/', OutboundException::REASON_PRIVATE];
        yield 'IPv4-mapped private' => ['https://v6.example.com/', OutboundException::REASON_PRIVATE];
        yield 'unresolvable host' => ['https://nowhere.example.com/', OutboundException::REASON_DNS];
        yield 'other port' => ['https://api.example.com:8443/', OutboundException::REASON_PORT];
        yield 'user information' => ['https://api.example.com@evil.example.org/', OutboundException::REASON_URL];
        yield 'loopback literal' => ['https://127.0.0.1/', OutboundException::REASON_HOST];
        yield 'not a URL' => ['api.example.com', OutboundException::REASON_URL];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testRefusedBeforeConnecting(string $url, string $reason): void
    {
        try {
            $this->client()->get($url);
            $this->fail('The request should be refused.');
        } catch (OutboundException $e) {
            $this->assertSame($reason, $e->reason);
        }

        $this->assertSame([], $this->transport->sent, 'Nothing was sent.');
    }

    public function testAllowListedLiteralIpMustStillBePublic(): void
    {
        $client = $this->client(['127.0.0.1']);

        $this->expectException(OutboundException::class);
        $this->expectExceptionMessage('private or reserved');
        $client->get('https://127.0.0.1/');
    }

    public function testRedirectsAreCheckedAgain(): void
    {
        // To an allowed public host: followed (relative Location too), 303 turns POST into GET.
        $this->transport->responses = [
            new OutboundResponse(303, ['location' => 'https://cdn.example.com/files/a'], ''),
            new OutboundResponse(302, ['location' => 'b'], ''),
            new OutboundResponse(200, [], 'done'),
        ];
        $response = $this->client()->post('https://api.example.com/upload', 'x');

        $this->assertSame('done', $response->body);
        $this->assertSame(['POST', 'GET', 'GET'], array_column($this->transport->sent, 'method'));
        $this->assertSame('https://cdn.example.com/files/b', $this->transport->sent[2]['url']);
        $this->assertNull($this->transport->sent[1]['body']);

        // To a private address, plain HTTP or a host outside the list: refused.
        foreach (['https://internal.example.com/' => 'private', 'http://cdn.example.com/' => 'scheme', 'https://evil.example.org/' => 'host'] as $location => $reason) {
            $this->transport->sent      = [];
            $this->transport->responses = [new OutboundResponse(302, ['location' => $location], '')];

            try {
                $this->client()->get('https://api.example.com/');
                $this->fail('Redirect to ' . $location . ' should be refused.');
            } catch (OutboundException $e) {
                $this->assertSame($reason, $e->reason);
                $this->assertCount(1, $this->transport->sent);
            }
        }
    }

    public function testTooManyRedirects(): void
    {
        $this->transport->responses = array_fill(0, 5, new OutboundResponse(302, ['location' => '/again'], ''));

        $this->expectException(OutboundException::class);
        $this->expectExceptionMessage('Too many redirects');
        $this->client()->get('https://api.example.com/');
    }

    public function testOversizeResponseIsRefused(): void
    {
        $this->transport->responses[] = new OutboundResponse(200, [], str_repeat('x', 2048));

        try {
            $this->client()->get('https://api.example.com/big');
            $this->fail('An oversize response should be refused.');
        } catch (OutboundException $e) {
            $this->assertSame(OutboundException::REASON_TOO_LARGE, $e->reason);
        }
    }

    public function testAllowListSources(): void
    {
        $config               = new Outbound();
        $config->allowedHosts = ['API.GitHub.com'];
        $config->hosts        = ' smtp.example.com , ';
        $list                 = new OutboundAllowList($config, static fn (): array => ['weather.example.net'], static fn (): mixed => '["*.example.org"]');

        $this->assertTrue($list->allows('api.github.com'));
        $this->assertTrue($list->allows('smtp.example.com.'));
        $this->assertTrue($list->allows('weather.example.net'), 'Declared by a module.');
        $this->assertTrue($list->allows('a.b.example.org'), 'Wildcard from the setting.');
        $this->assertFalse($list->allows('example.org'), 'A wildcard covers subdomains only.');
        $this->assertFalse($list->allows('evilexample.org'));
        $this->assertFalse($list->allows('github.com'));
        $this->assertFalse($list->allows(''));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function addresses(): iterable
    {
        foreach (['93.184.216.34', '8.8.8.8', '2606:4700::1111'] as $ip) {
            yield $ip => [$ip, true];
        }

        foreach (['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.0.1', '169.254.169.254', '100.64.0.1', '0.0.0.0', '224.0.0.1',
            '255.255.255.255', '::1', '::', 'fc00::1', 'fd12::1', 'fe80::1', '::ffff:127.0.0.1', '::ffff:10.0.0.1', '64:ff9b::a00:1',
            '2001:db8::1', '::127.0.0.1', 'not-an-ip'] as $ip) {
            yield $ip => [$ip, false];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('addresses')]
    public function testOnlyPublicAddresses(string $ip, bool $public): void
    {
        $this->assertSame($public, IpGuard::isPublic($ip));
    }

    public function testRelativeLocations(): void
    {
        $this->assertSame('https://a.example.com/x/y', OutboundClient::absolute('/x/y', 'https://a.example.com/p/q'));
        $this->assertSame('https://a.example.com/p/z', OutboundClient::absolute('z', 'https://a.example.com/p/q'));
        $this->assertSame('https://b.example.com/', OutboundClient::absolute('//b.example.com/', 'https://a.example.com/'));
        $this->assertSame('http://b.example.com/', OutboundClient::absolute('http://b.example.com/', 'https://a.example.com/'));
    }

    public function testSmtpHostMustBeAllowed(): void
    {
        $config               = new Outbound();
        $config->allowedHosts = ['smtp.example.com'];
        $list                 = new OutboundAllowList($config, static fn (): array => [], static fn (): mixed => null);

        $email = new GuardedEmail(['protocol' => 'smtp', 'SMTPHost' => 'smtp.evil.example.org']);
        $email->setAllowList($list)->setFrom('a@example.com')->setTo('b@example.com')->setSubject('x')->setMessage('x');
        $this->assertFalse($email->transportAllowed());
        $this->assertFalse($email->send(), 'Not sent to a host outside the allow-list.');

        $email = new GuardedEmail(['protocol' => 'smtp', 'SMTPHost' => 'smtp.example.com']);
        $this->assertTrue($email->setAllowList($list)->transportAllowed());

        $this->assertTrue((new GuardedEmail(['protocol' => 'mail']))->setAllowList($list)->transportAllowed(), 'Local delivery.');
        $this->assertInstanceOf(GuardedEmail::class, \Config\Services::email(config(EmailConfig::class), false));
    }

    /**
     * @param list<string> $extraHosts
     */
    private function client(array $extraHosts = []): OutboundClient
    {
        $config               = new Outbound();
        $config->allowedHosts = ['api.example.com', 'cdn.example.com', 'internal.example.com', 'mixed.example.com', 'v6.example.com', 'nowhere.example.com', ...$extraHosts];
        $config->maxBytes     = 1024;
        $list                 = new OutboundAllowList($config, static fn (): array => [], static fn (): mixed => null);

        return new OutboundClient($config, $list, $this->transport, static fn (string $host): array => self::DNS[$host] ?? []);
    }
}

/**
 * Records the requests and answers with queued responses, enforcing the size limit like cURL.
 */
final class FakeTransport implements OutboundTransport
{
    /** @var list<OutboundResponse> */
    public array $responses = [];

    /** @var list<array<string, mixed>> */
    public array $sent = [];

    public function send(string $method, string $url, array $headers, ?string $body, string $ip, int $port, int $connectTimeout, int $timeout, int $maxBytes): OutboundResponse
    {
        $this->sent[] = compact('method', 'url', 'headers', 'body', 'ip', 'port', 'maxBytes');
        $response     = array_shift($this->responses) ?? new OutboundResponse(200, [], '');

        if (strlen($response->body) > $maxBytes) {
            throw new OutboundException(OutboundException::REASON_TOO_LARGE, 'Too large.');
        }

        return $response;
    }
}
