<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Production forces HTTPS and `Secure` cookies everywhere except PHP's development server
 * (`php spark serve`) answering a browser on the same computer (D-19-05), so a fresh clone can
 * open the web installer at http://localhost without a certificate.
 *
 * @internal
 */
final class LocalDevServerTest extends CIUnitTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public static function requests(): iterable
    {
        // sapi, client address, Host header, local?
        yield 'spark serve, localhost' => ['cli-server', '127.0.0.1', 'localhost', true];
        yield 'spark serve, localhost:8080' => ['cli-server', '127.0.0.1', 'localhost:8080', true];
        yield 'spark serve, IPv6' => ['cli-server', '::1', '[::1]:8080', true];
        yield 'spark serve, 127.0.0.1' => ['cli-server', '127.0.0.1', '127.0.0.1:80', true];
        yield 'spark serve, app.localhost' => ['cli-server', '127.0.0.1', 'kpskeletor.localhost', true];
        yield 'spark serve, LOCALHOST' => ['cli-server', '127.0.0.1', 'LOCALHOST', true];
        // A web server never qualifies, not even a proxy on the same machine with Host 127.0.0.1.
        yield 'php-fpm behind nginx' => ['fpm-fcgi', '127.0.0.1', '127.0.0.1:8080', false];
        yield 'apache, localhost' => ['apache2handler', '127.0.0.1', 'localhost', false];
        // Another computer, or a forged Host header from another computer.
        yield 'spark serve, LAN client' => ['cli-server', '192.168.1.20', 'localhost:8080', false];
        yield 'spark serve, LAN host' => ['cli-server', '127.0.0.1', '192.168.1.10:8080', false];
        yield 'spark serve, real domain' => ['cli-server', '127.0.0.1', 'intranet.example.com', false];
        yield 'spark serve, look-alike' => ['cli-server', '127.0.0.1', 'localhost.example.com', false];
        yield 'spark serve, no host' => ['cli-server', '127.0.0.1', '', false];
        yield 'spark serve, bad client' => ['cli-server', 'localhost', 'localhost', false];
    }

    #[DataProvider('requests')]
    public function testOnlyTheLocalDevelopmentServerQualifies(string $sapi, string $client, string $host, bool $local): void
    {
        $this->assertSame($local, App::isLocalDevServer(['REMOTE_ADDR' => $client, 'HTTP_HOST' => $host], $sapi));
    }

    public function testTheCommandLineIsNeverTheLocalServer(): void
    {
        // PHPUnit runs on the cli SAPI: the defaults (PHP_SAPI, $_SERVER) never qualify.
        $this->assertFalse(App::isLocalDevServer());
    }

    public function testHttpsAndSecureCookiesStillDependOnProductionOnly(): void
    {
        foreach (['App', 'Cookie'] as $config) {
            $this->assertStringContainsString("ENVIRONMENT === 'production' && ! ", (string) file_get_contents(APPPATH . "Config/{$config}.php"), $config);
        }
    }
}
