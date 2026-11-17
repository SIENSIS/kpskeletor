<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\ContentSecurityPolicy as CspConfig;
use Config\Cookie;
use Config\SecurityHeaders as SecurityHeadersConfig;
use Modules\Core\Config\Packages;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Security\Check\Checks\ConfigurationCheck;
use Modules\Core\Security\Check\Checks\DependenciesCheck;
use Modules\Core\Security\Check\Checks\InstallationCheck;
use Modules\Core\Security\Check\Checks\OutboundCheck;
use Modules\Core\Security\Check\Checks\SecretsCheck;
use Modules\Core\Security\Check\ComposerAudit;
use Modules\Core\Security\Check\EffectivePolicy;
use Modules\Core\Security\Check\FileAccess;
use Modules\Core\Security\Check\SecretScanner;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityReport;
use RuntimeException;

/**
 * `security:check` (docs/security.md, "Production checklist"): every check reports errors on a
 * development configuration and passes on a hardened one (acceptance criterion 12), plus the
 * pieces: unsafe CSP sources, file modes, the secret scanner, the stored `composer audit`, the
 * vendored checksums. The modules' own checks are tested in their modules (`*SecurityCheckTest`).
 *
 * @internal
 */
final class SecurityCheckTest extends CIUnitTestCase
{
    private const MASK = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/kp-security-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->dir);
        parent::tearDown();
    }

    public function testFailsOnADevelopmentConfiguration(): void
    {
        $report = (new SecurityChecker($this->developmentChecks()))->run();

        $this->assertTrue($report->hasErrors());
        $this->assertSame('error', $report->status());

        foreach ([
            'configuration.environment', 'configuration.debug', 'configuration.https', 'configuration.cookies',
            'configuration.hostnames', 'configuration.csp', 'secrets.encryptionKey', 'secrets.maskKey',
            'secrets.envFile', 'secrets.trackedFiles', 'installation.lock', 'installation.demo',
            'installation.webWritable', 'installation.public', 'dependencies.vendor',
        ] as $id) {
            $this->assertContains($id, $report->errorIds(), $id);
        }

        $statuses = $this->statuses($report);
        $this->assertSame('warning', $statuses['installation.writableModes']);
        $this->assertSame('warning', $statuses['dependencies.audit']);
        $this->assertSame('warning', $statuses['configuration.hsts']);
        $this->assertSame('warning', $statuses['installation.packages'], 'Unsigned packages accepted.');
        $this->assertSame('warning', $statuses['installation.uploadsHtaccess']);
    }

    public function testPassesOnAHardenedConfiguration(): void
    {
        $report = (new SecurityChecker($this->hardenedChecks()))->run();

        $this->assertSame([], $report->errorIds(), json_encode($report->toArray(), JSON_PRETTY_PRINT) ?: '');
        $this->assertNotContains('warning', $this->statuses($report));
        $this->assertNotContains('error', $this->statuses($report));
        $this->assertSame('info', $report->status(), 'The outbound allow-list is information only.');
    }

    public function testOnlyRunsOneGroupAndAThrowingCheckIsAnError(): void
    {
        $throwing = new class () implements SecurityCheck {
            public function groups(): array
            {
                return ['accounts'];
            }

            public function run(SecurityReport $report): void
            {
                throw new RuntimeException('database gone');
            }
        };

        $checker = new SecurityChecker([...$this->hardenedChecks(), $throwing]);
        $only    = $checker->run('secrets');

        $this->assertNotSame([], $only->results());
        $this->assertSame(['secrets'], array_keys($only->grouped()));

        $accounts = $checker->run('accounts');
        $this->assertTrue($accounts->hasErrors());
        $this->assertStringContainsString('database gone', $accounts->results()[0]->message);
        $this->assertContains('accounts', $checker->groups());
    }

    public function testReportRoundTripAndGrouping(): void
    {
        $report = new SecurityReport('2026-11-17 10:00:00');
        $report->info('outbound', 'outbound.allowList', 'Allow-list');
        $report->error('configuration', 'configuration.https', 'HTTPS', 'off', 'turn it on');
        $report->warning('mymodule', 'mymodule.thing', 'Thing');

        $copy = SecurityReport::fromArray(json_decode((string) json_encode($report->toArray()), true));

        $this->assertSame($report->toArray(), $copy->toArray());
        $this->assertSame(['configuration', 'outbound', 'mymodule'], array_keys($copy->grouped()));
        $this->assertSame(['ok' => 0, 'info' => 1, 'warning' => 1, 'error' => 1], $copy->counts());
        $this->assertSame(['configuration.https'], $copy->errorIds());
    }

    public function testUnsafeOrReportOnlyCspIsAnError(): void
    {
        $csp            = new CspConfig();
        $csp->scriptSrc = ['self', "'unsafe-inline'"];
        $this->assertContains('configuration.csp', $this->configurationReport($csp)->errorIds());

        $this->assertContains("script-src 'unsafe-inline'", EffectivePolicy::unsafeSources("default-src 'self'; script-src 'self' 'unsafe-inline'"));
        $this->assertSame(['default-src *'], EffectivePolicy::unsafeSources('default-src *'));
        $this->assertSame([], EffectivePolicy::unsafeSources("default-src 'self'; img-src 'self' data: https://cdn.example.com"));

        $reportOnly             = new CspConfig();
        $reportOnly->reportOnly = true;
        $report                 = $this->configurationReport($reportOnly);
        $this->assertContains('configuration.csp', $report->errorIds());
        $this->assertStringContainsString('Report-Only', lang('SecurityCheck.csp.reportOnly'));

        $this->assertNotContains('configuration.csp', $this->configurationReport(new CspConfig())->errorIds());
    }

    public function testEffectivePolicyIncludesModuleAndSettingSources(): void
    {
        $policy = new EffectivePolicy(new CspConfig(), new SecurityHeadersConfig(), ['frame-src' => ['https://www.youtube-nocookie.com']], ['img-src' => ['https://images.example.com']], 'production');
        $csp    = $policy->csp(true);

        $this->assertSame('Content-Security-Policy', $csp['name']);
        $directives = EffectivePolicy::directives($csp['value']);
        $this->assertContains('https://www.youtube-nocookie.com', $directives['frame-src']);
        $this->assertContains('https://images.example.com', $directives['img-src']);
        $this->assertSame(["'none'"], $directives['object-src']);
        $this->assertStringNotContainsString('unsafe', $csp['value']);
        $this->assertArrayHasKey('Strict-Transport-Security', $policy->headers());
    }

    public function testFileModes(): void
    {
        $file = $this->dir . '/file';
        file_put_contents($file, 'x');

        chmod($file, 0640);
        $this->assertFalse(FileAccess::looserThan($file, 0640));
        $this->assertFalse(FileAccess::readableByOthers($file));
        chmod($file, 0600);
        $this->assertFalse(FileAccess::looserThan($file, 0640), 'Stricter modes are accepted.');
        chmod($file, 0644);
        $this->assertTrue(FileAccess::looserThan($file, 0640));
        $this->assertTrue(FileAccess::readableByOthers($file));
        chmod($file, 0666);
        $this->assertTrue(FileAccess::worldWritable($file));

        $console = new FileAccess('', null, true);
        $this->assertTrue($console->writableByWebUser($file), 'Writable by everybody: known from the console.');
        chmod($file, 0640);
        $this->assertNull($console->writableByWebUser($file), 'Otherwise unknown without productioncheck.webUser.');
        $this->assertFalse($console->knowsWebUser());
        $this->assertTrue((new FileAccess('', null, false))->knowsWebUser(), 'A web request runs as the web user.');
    }

    public function testSecretScanner(): void
    {
        $key = '-----BEGIN ' . 'PRIVATE KEY-----';
        file_put_contents($this->dir . '/leak.txt', "hello\n{$key}\nabc\n");
        file_put_contents($this->dir . '/fixture.txt', $key . ' // ' . SecretScanner::IGNORE_MARKER . "\n");
        file_put_contents($this->dir . '/env.md', "encryption.key = hex2bin:0011223344556677889900\napp.maskKey = <64 hex characters>\ndatabase.default.password = choose-a-password\n");
        file_put_contents($this->dir . '/token.php', '<?php $t = "gh' . 'p_' . str_repeat('a1B2', 9) . '";');
        mkdir($this->dir . '/vendor');
        file_put_contents($this->dir . '/vendor/lib.txt', $key);

        $findings = (new SecretScanner($this->dir . '/'))->scan(['leak.txt', 'fixture.txt', 'env.md', 'token.php', 'vendor/lib.txt']);

        $this->assertSame(['leak.txt:2 (private key)', 'env.md:1 (.env secret)', 'token.php:1 (GitHub token)'], $findings);
        $this->assertNull((new SecretScanner($this->dir . '/'))->scan(), 'Not a git checkout.');
    }

    public function testComposerAuditResultAndAge(): void
    {
        $config       = new ProductionCheck();
        $config->path = $this->dir . '/security';
        $audit        = new ComposerAudit($config, static fn (array $command): array => ['code' => 1, 'output' => 'warning: x' . "\n" . (string) json_encode(['advisories' => ['acme/lib' => [['cve' => 'CVE-1'], ['cve' => 'CVE-2']]], 'abandoned' => []])]);

        $status = $audit->run();
        $this->assertSame(2, $status['advisories']);
        $this->assertSame(['acme/lib'], $status['packages']);
        $this->assertSame('0640', sprintf('%04o', fileperms($audit->file()) & 0777));

        $report = new SecurityReport();
        (new DependenciesCheck($audit, $this->vendorFixture(), $config))->run($report);
        $this->assertSame(['dependencies.audit'], $report->errorIds());

        $audit->store((string) json_encode(['advisories' => [], 'abandoned' => []]));
        $fresh = new SecurityReport();
        (new DependenciesCheck($audit, $this->vendorFixture(), $config))->run($fresh);
        $this->assertSame('ok', $this->statuses($fresh)['dependencies.audit']);

        $stale = new SecurityReport();
        (new DependenciesCheck($audit, $this->vendorFixture(), $config, time() + 8 * DAY))->run($stale);
        $this->assertSame('warning', $this->statuses($stale)['dependencies.audit']);

        $failed = $audit->store('composer: command not found', 127);
        $this->assertSame('composer: command not found', $failed['error']);
    }

    public function testVendorChecksums(): void
    {
        $vendor = $this->vendorFixture();
        $report = new SecurityReport();
        (new DependenciesCheck(null, $vendor, $this->config()))->run($report);
        $this->assertSame('ok', $this->statuses($report)['dependencies.vendor']);

        file_put_contents($vendor . '/lib/lib.js', 'tampered');
        $report = new SecurityReport();
        (new DependenciesCheck(null, $vendor, $this->config()))->run($report);
        $this->assertContains('dependencies.vendor', $report->errorIds());
    }

    /**
     * @return list<SecurityCheck>
     */
    private function developmentChecks(): array
    {
        $root = $this->project(false);

        $app                            = new App();
        $app->baseURL                   = 'http://localhost:8080/';
        $app->forceGlobalSecureRequests = false;
        $cookie                         = new Cookie();
        $cookie->secure                 = false;
        $csp                            = new CspConfig();
        $csp->styleSrc                  = ['self', "'unsafe-inline'"];
        $headers                        = new SecurityHeadersConfig();

        return [
            new ConfigurationCheck('development', $app, $cookie, $csp, new EffectivePolicy($csp, $headers, [], [], 'development'), true, true),
            new SecretsCheck('', '', '', '', $root, ['app/Config/Secrets.php:3 (private key)']),
            new InstallationCheck($root, $root . 'writable/', $root . 'public/', $this->config(), new FileAccess('', static fn (string $path): bool => true), static fn (): array => ['News'], new Packages()),
            new DependenciesCheck(new ComposerAudit($this->config()), $root . 'missing-vendor', $this->config()),
            new OutboundCheck([], []),
        ];
    }

    /**
     * @return list<SecurityCheck>
     */
    private function hardenedChecks(): array
    {
        $root = $this->project(true);

        $app                            = new App();
        $app->baseURL                   = 'https://www.example.org/';
        $app->forceGlobalSecureRequests = true;
        $cookie                         = new Cookie();
        $cookie->secure                 = true;
        $csp                            = new CspConfig();
        $headers                        = new SecurityHeadersConfig();
        $config                         = $this->config();

        (new ComposerAudit($config))->store((string) json_encode(['advisories' => [], 'abandoned' => []]));

        return [
            new ConfigurationCheck('production', $app, $cookie, $csp, new EffectivePolicy($csp, $headers, [], [], 'production'), false, false),
            new SecretsCheck('hex2bin:' . str_repeat('ab', 32), self::MASK, self::MASK, self::MASK, $root, []),
            new InstallationCheck($root, $root . 'writable/', $root . 'public/', $config, new FileAccess('', static fn (string $path): bool => str_starts_with($path, $root . 'writable')), static fn (): array => [], $this->signedPackages()),
            new DependenciesCheck(new ComposerAudit($config), $this->vendorFixture(), $config),
            new OutboundCheck(['api.github.com'], ['Updates' => ['api.github.com']]),
        ];
    }

    /**
     * A project tree: hardened (modes, lock, clean public/) or as a fresh development checkout.
     */
    private function project(bool $hardened): string
    {
        $root = $this->dir . '/' . ($hardened ? 'prod' : 'dev') . '/';
        mkdir($root . 'writable/cache', 0777, true);
        mkdir($root . 'public/uploads', 0777, true);
        mkdir($root . 'modules', 0777, true);
        file_put_contents($root . 'public/index.php', '<?php');
        file_put_contents($root . '.env', 'CI_ENVIRONMENT = production');
        file_put_contents($root . 'writable/cache/data', 'x');

        if ($hardened) {
            file_put_contents($root . 'writable/install.lock', '{}');
            chmod($root . '.env', 0640);
            chmod($root . 'writable', 0750);
            chmod($root . 'writable/cache', 0700);
            chmod($root . 'writable/cache/data', 0600);
            chmod($root . 'writable/install.lock', 0640);
            file_put_contents($root . 'public/uploads/.htaccess', 'Require all denied');
        } else {
            chmod($root . '.env', 0644);
            chmod($root . 'writable', 0755);
            chmod($root . 'writable/cache/data', 0644);
            file_put_contents($root . 'public/uploads/shell.php', '<?php');
        }

        return $root;
    }

    private function signedPackages(): Packages
    {
        $packages                   = new Packages();
        $packages->requireSignature = true;
        $packages->publicKeys       = ['RWQf6LRCGA9i53mlYecO4IzT51TGPpvWucNSCh1CBM0QTaLn73Y7GFO3'];

        return $packages;
    }

    private function config(): ProductionCheck
    {
        $config       = new ProductionCheck();
        $config->path = $this->dir . '/security';

        return $config;
    }

    private function vendorFixture(): string
    {
        $vendor = $this->dir . '/vendor-assets';

        if (! is_dir($vendor)) {
            mkdir($vendor . '/lib', 0777, true);
            file_put_contents($vendor . '/lib/lib.js', 'console.log(1);');
            file_put_contents($vendor . '/vendor.json', (string) json_encode(['lib' => ['version' => '1.0.0', 'files' => ['lib.js' => hash('sha256', 'console.log(1);')]]]));
        }

        return $vendor;
    }

    private function configurationReport(CspConfig $csp): SecurityReport
    {
        $app                            = new App();
        $app->baseURL                   = 'https://www.example.org/';
        $app->forceGlobalSecureRequests = true;
        $cookie                         = new Cookie();
        $cookie->secure                 = true;
        $report                         = new SecurityReport();

        (new ConfigurationCheck('production', $app, $cookie, $csp, new EffectivePolicy($csp, new SecurityHeadersConfig(), [], [], 'production'), false, false))->run($report);

        return $report;
    }

    /**
     * @return array<string, string> id => status
     */
    private function statuses(SecurityReport $report): array
    {
        $statuses = [];

        foreach ($report->results() as $result) {
            $statuses[$result->id] = $result->status;
        }

        return $statuses;
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && ! is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }

            @rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
    }
}
