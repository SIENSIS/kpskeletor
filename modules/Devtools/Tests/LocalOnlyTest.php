<?php

declare(strict_types=1);

namespace Modules\Devtools\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Devtools\Filters\LocalOnly;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The `devtools` filter's rule (lock 4): loopback client and a local `Host`.
 *
 * @internal
 */
final class LocalOnlyTest extends CIUnitTestCase
{
    /**
     * @return iterable<string, array{0: string|null, 1: string|null, 2: bool}>
     */
    public static function requests(): iterable
    {
        yield 'spark serve' => ['127.0.0.1', 'localhost:8080', true];
        yield 'loopback host' => ['127.0.0.1', '127.0.0.1:8080', true];
        yield 'loopback range' => ['127.1.2.3', 'localhost', true];
        yield 'ipv6' => ['::1', '[::1]:8080', true];
        yield 'mapped ipv4' => ['::ffff:127.0.0.1', 'localhost', true];
        yield 'upper case host' => ['127.0.0.1', 'LOCALHOST:8080', true];
        yield 'lan client' => ['192.168.1.10', 'localhost:8080', false];
        yield 'public client' => ['203.0.113.5', 'localhost', false];
        yield 'ipv6 client' => ['2001:db8::1', '[::1]', false];
        yield 'foreign host' => ['127.0.0.1', 'dev.example.com', false];
        yield 'lan host' => ['127.0.0.1', '192.168.1.20:8080', false];
        yield 'look-alike host' => ['127.0.0.1', 'localhost.example.com', false];
        yield 'host with path' => ['127.0.0.1', 'localhost/evil', false];
        yield 'empty host' => ['127.0.0.1', '', false];
        yield 'no host' => ['127.0.0.1', null, false];
        yield 'no client' => [null, 'localhost', false];
        yield 'garbage client' => ['localhost', 'localhost', false];
        yield 'mapped lan' => ['::ffff:192.168.1.10', 'localhost', false];
    }

    #[DataProvider('requests')]
    public function testIsLocal(?string $address, ?string $host, bool $expected): void
    {
        $this->assertSame($expected, LocalOnly::isLocal($address, $host));
    }
}
