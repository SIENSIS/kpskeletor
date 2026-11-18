<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Config\Factories;
use Config\Outbound;
use Config\Services;
use Modules\Core\Config\Version;
use Modules\Core\Http\OutboundAllowList;
use Modules\Core\Http\OutboundClient;
use Modules\Core\Http\OutboundResponse;
use Modules\Dashboard\Tests\DashboardTestCase;
use Modules\Updates\Config\Updates as UpdatesConfig;
use Modules\Updates\Services\ReleaseChecker;
use Modules\Updates\Services\ReleaseSource;
use Modules\Updates\Services\Updater;

/**
 * Base of the Updates feature tests: a private folder for `writable/updates` and the
 * installation updated by `app:update`, a fake repository served through the real
 * OutboundClient (fake transport and DNS), and the checker injected as the `updatechecker`
 * service.
 */
abstract class UpdatesTestCase extends DashboardTestCase
{
    protected const REPOSITORY = 'github:acme/app';

    protected const RELEASES = 'https://api.github.com/repos/acme/app/releases?per_page=50';

    protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'Updates'];

    protected string $root = '';

    protected UpdatesConfig $config;

    protected FakeTransport $transport;

    /** @var array<string, array{version: string, repository: string}> */
    protected array $moduleRepositories = [];

    /** The installed Core version (`Version::CORE`) and releases relative to it, so the tests follow every release. */
    protected static string $installed = '';

    protected static string $next = '';

    protected static string $after = '';

    protected static string $older = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$installed = Version::CORE;
        self::$next      = self::minorAfter(Version::CORE, 1);
        self::$after     = self::minorAfter(Version::CORE, 2);
        self::$older     = self::before(Version::CORE);
    }

    /**
     * `<major>.<minor + $steps>.0` of a version.
     */
    private static function minorAfter(string $version, int $steps): string
    {
        [$major, $minor] = self::parts($version);

        return sprintf('%d.%d.0', $major, $minor + $steps);
    }

    /**
     * A version just below the given one.
     */
    private static function before(string $version): string
    {
        [$major, $minor, $patch] = self::parts($version);

        return match (true) {
            $patch > 0 => sprintf('%d.%d.%d', $major, $minor, $patch - 1),
            $minor > 0 => sprintf('%d.%d.9', $major, $minor - 1),
            default    => sprintf('%d.9.9', $major - 1),
        };
    }

    /**
     * @return array{int, int, int}
     */
    private static function parts(string $version): array
    {
        $numbers = array_map('intval', explode('.', explode('-', $version)[0]));

        return [$numbers[0], $numbers[1] ?? 0, $numbers[2] ?? 0];
    }

    /** Download folder of the next release's assets. */
    protected static function download(): string
    {
        return 'https://github.com/acme/app/releases/download/v' . self::$next . '/';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/kpupdates-test-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/state', 0750, true);
        mkdir($this->root . '/app', 0755, true);

        $config             = new UpdatesConfig();
        $config->repository = self::REPOSITORY;
        $config->path       = $this->root . '/state';
        $config->rootPath   = $this->root . '/app';
        $config->method     = 'archive';
        $this->config       = $config;
        Factories::injectMock('config', UpdatesConfig::class, $config);
        Factories::injectMock('config', 'Updates', $config);

        $this->transport = new FakeTransport();
        Services::injectMock('updatechecker', $this->checker());
    }

    protected function tearDown(): void
    {
        Updater::removeFolder($this->root);
        Factories::reset('config');
        parent::tearDown();
    }

    protected function client(int $maxBytes = 10485760): OutboundClient
    {
        $outbound               = new Outbound();
        $outbound->allowedHosts = ['api.github.com', 'github.com', 'objects.githubusercontent.com', 'gitlab.com'];
        $outbound->maxBytes     = $maxBytes;

        return new OutboundClient($outbound, new OutboundAllowList($outbound, static fn (): array => [], static fn (): mixed => null), $this->transport, static fn (string $host): array => ['93.184.216.34']);
    }

    protected function source(string $repository = self::REPOSITORY, string $token = ''): ReleaseSource
    {
        return new ReleaseSource($repository, $token, $this->client());
    }

    protected function checker(): ReleaseChecker
    {
        return new ReleaseChecker($this->config, fn (string $repository): ReleaseSource => $this->source($repository), fn (): array => $this->moduleRepositories);
    }

    /**
     * Publishes releases in the fake GitHub repository.
     *
     * @param list<array<string, mixed>> $releases
     */
    protected function publish(array $releases, string $url = self::RELEASES): void
    {
        $this->transport->routes[$url] = new OutboundResponse(200, ['content-type' => 'application/json'], (string) json_encode($releases));
    }

    /**
     * One GitHub release.
     *
     * @param array<string, string> $assets name => browser download URL
     *
     * @return array<string, mixed>
     */
    protected static function release(string $tag, string $body = '', array $assets = [], bool $prerelease = false, bool $draft = false): array
    {
        $list = [];
        $id   = 1;

        foreach ($assets as $name => $url) {
            $list[] = ['name' => $name, 'browser_download_url' => $url, 'url' => 'https://api.github.com/repos/acme/app/releases/assets/' . $id++];
        }

        return [
            'tag_name' => $tag, 'name' => 'Release ' . $tag, 'body' => $body, 'draft' => $draft, 'prerelease' => $prerelease,
            'html_url' => 'https://github.com/acme/app/releases/tag/' . $tag, 'published_at' => '2026-11-15T10:00:00Z', 'assets' => $list,
        ];
    }
}
