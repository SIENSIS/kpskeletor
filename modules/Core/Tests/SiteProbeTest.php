<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Security\Check\Probe\ProbeClient;
use Modules\Core\Security\Check\Probe\ProbeResponse;
use Modules\Core\Security\Check\Probe\SiteProbe;
use Modules\Core\Security\Check\SecurityReport;
use RuntimeException;

/**
 * `security:check --probe`: a dedicated client limited to the site's own hosts (HTTPS, plain
 * HTTP only for the redirect check), and each probe passing against a hardened site and failing
 * against a misconfigured one. The PHP probe file in uploads/ is always removed.
 *
 * @internal
 */
final class SiteProbeTest extends CIUnitTestCase
{
    private string $public = '';

    /** @var list<string> */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->public = sys_get_temp_dir() . '/kp-probe-' . bin2hex(random_bytes(4));
        mkdir($this->public . '/uploads', 0777, true);
    }

    protected function tearDown(): void
    {
        @rmdir($this->public . '/uploads');
        @rmdir($this->public);
        parent::tearDown();
    }

    public function testTheClientOnlyCallsTheSiteItself(): void
    {
        $client = new ProbeClient(SiteProbe::siteHosts($this->app()), 5, static fn (): ProbeResponse => new ProbeResponse(200));

        $this->assertTrue($client->allows('https://www.example.org/login'));
        $this->assertTrue($client->allows('https://media.example.org/'));
        $this->assertFalse($client->allows('http://www.example.org/'), 'Plain HTTP only for the redirect check.');
        $this->assertTrue($client->allows('http://www.example.org/', true));
        $this->assertFalse($client->allows('https://evil.example.com/'));
        $this->assertFalse($client->allows('https://user:pass@www.example.org/'));
        $this->assertFalse($client->allows('file:///etc/passwd'));

        $this->expectException(RuntimeException::class);
        $client->request('GET', 'https://169.254.169.254/latest/meta-data/');
    }

    public function testAHardenedSitePasses(): void
    {
        $report = $this->probe(true);

        $this->assertSame([], $report->errorIds(), (string) json_encode($report->toArray(), JSON_PRETTY_PRINT));
        $ids = array_map(static fn ($result): string => $result->id, $report->results());
        $this->assertSame(['probe.headers', 'probe.hidden', 'probe.uploads', 'probe.redirect', 'probe.login', 'probe.https', 'probe.progress'], $ids);
        $this->assertSame([], glob($this->public . '/uploads/*') ?: [], 'The probe file is removed.');
        $this->assertNotContains('https://kp-security-probe.invalid/', $this->requests, 'Never a request to another site.');
    }

    public function testAMisconfiguredSiteFails(): void
    {
        $report = $this->probe(false);

        $this->assertSame(['probe.headers', 'probe.hidden', 'probe.uploads', 'probe.redirect', 'probe.login', 'probe.https', 'probe.progress'], $report->errorIds());
        $this->assertSame([], glob($this->public . '/uploads/*') ?: [], 'The probe file is removed even when it ran.');
    }

    public function testAnotherSiteIsRefused(): void
    {
        $report = new SecurityReport();
        (new SiteProbe('https://evil.example.com/', null, new ProductionCheck(), $this->app(), $this->public, []))->run($report);

        $this->assertSame(['probe.target'], $report->errorIds());
    }

    private function probe(bool $hardened): SecurityReport
    {
        $public    = $this->public;
        $attempts  = 0;
        $transport = function (string $method, string $url, array $headers, ?string $body) use ($hardened, $public, &$attempts): ProbeResponse {
            $this->requests[] = $method . ' ' . $url;
            $path             = (string) parse_url($url, PHP_URL_PATH);
            $query            = (string) parse_url($url, PHP_URL_QUERY);
            $secure           = [
                'content-security-policy'   => $hardened ? "default-src 'self'; script-src 'self' 'nonce-abc'" : "script-src 'self' 'unsafe-inline'",
                'strict-transport-security' => 'max-age=31536000',
                'x-content-type-options'    => 'nosniff',
                'x-frame-options'           => 'DENY',
                'referrer-policy'           => 'strict-origin-when-cross-origin',
                'permissions-policy'        => 'camera=()',
            ];

            if (str_starts_with($url, 'http://')) {
                return $hardened ? new ProbeResponse(301, ['location' => 'https://www.example.org/']) : new ProbeResponse(200);
            }

            if (str_starts_with($path, '/uploads/')) {
                // A server that executes PHP would answer with the probe's output.
                $code = (string) @file_get_contents($public . $path);

                return $hardened ? new ProbeResponse(403) : new ProbeResponse(200, [], preg_match("/strrev\\('([^']+)'\\)/", $code, $m) === 1 ? strrev($m[1]) : '');
            }

            if (str_contains($query, 'return=')) {
                return $hardened ? new ProbeResponse(200) : new ProbeResponse(302, ['location' => 'https://kp-security-probe.invalid/']);
            }

            if ($path === '/login') {
                if ($method === 'GET') {
                    return new ProbeResponse(200, ['set-cookie' => 'ci_session=abc; HttpOnly'], '<input type="hidden" name="csrf_test_name" value="tok' . $attempts . '">');
                }

                $attempts++;

                return new ProbeResponse($hardened && $attempts >= 5 ? 429 : 200);
            }

            if (str_starts_with($path, '/progress/')) {
                return new ProbeResponse(200, [], $hardened ? '{"status":"unknown"}' : '{"status":"done","password":"x"}');
            }

            if ($path === '/' || $path === '') {
                return new ProbeResponse(200, $secure);
            }

            return new ProbeResponse($hardened || $path !== '/.env' ? 404 : 200);
        };

        $client = new ProbeClient(SiteProbe::siteHosts($this->app()), 5, $transport);
        $report = new SecurityReport();
        (new SiteProbe('https://www.example.org/', $client, new ProductionCheck(), $this->app(), $this->public, ['progress/backup/([^/]+)']))->run($report);

        return $report;
    }

    private function app(): App
    {
        $app                   = new App();
        $app->baseURL          = 'https://www.example.org/';
        $app->allowedHostnames = ['media.example.org'];

        return $app;
    }
}
