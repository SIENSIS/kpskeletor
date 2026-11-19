<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Install\EnvWriter;
use Modules\Core\Notifications\NotificationMessage;
use Modules\Core\Routing\BackendPath;
use Modules\Core\Routing\BackendPathChanged;

/**
 * The back-office prefix helpers (D-40-01) and the stored form of back-office links (D-40-02).
 *
 * @internal
 */
final class BackendPathTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        config('App')->backendPath = 'backend';
        parent::tearDown();
    }

    public function testDefaultIsBackend(): void
    {
        $this->assertSame('backend', BackendPath::current());
        $this->assertSame('backend', backend_path());
        $this->assertSame('backend/news/posts', backend_path('news/posts'));
        $this->assertSame('backend/news?page=2', backend_path('news?page=2'));
        $this->assertSame('backend?return=x', backend_path('?return=x'));
        $this->assertSame(site_url('backend/news'), backend_url('news'));
        $this->assertSame('backend/news/read/7', backend_link('news/read/7'));
    }

    public function testFollowsTheConfiguration(): void
    {
        config('App')->backendPath = 'admin';

        $this->assertSame('admin', backend_path());
        $this->assertSame('admin/news/posts', backend_path('/news/posts'));
        $this->assertSame(site_url('admin'), backend_url());
        // Stored links keep the canonical prefix.
        $this->assertSame('backend/news/read/7', backend_link('news/read/7'));
        $this->assertTrue(BackendPath::contains('/admin/news'));
        $this->assertTrue(BackendPath::contains('admin'));
        $this->assertFalse(BackendPath::contains('administration'));
        $this->assertFalse(BackendPath::contains('backend/news'));
    }

    public function testAnInvalidConfiguredValueFallsBackToTheDefault(): void
    {
        foreach (['', '../x', 'Admin Panel', 'a', 'admin/panel'] as $value) {
            config('App')->backendPath = $value;
            $this->assertSame('backend', BackendPath::current(), $value);
        }
    }

    public function testStoredLinksAreResolvedUnderTheInstalledPrefix(): void
    {
        $this->assertSame('backend/news', BackendPath::resolve('backend/news'));

        config('App')->backendPath = 'admin';

        $this->assertSame('admin/news/read/7', BackendPath::resolve('backend/news/read/7'));
        $this->assertSame('/admin/news', BackendPath::resolve('/backend/news'));
        $this->assertSame('admin', BackendPath::resolve('backend'));
        $this->assertSame('admin?x=1', BackendPath::resolve('backend?x=1'));
        $this->assertSame('backendish/news', BackendPath::resolve('backendish/news'));
        $this->assertSame('news/hello', BackendPath::resolve('news/hello'));
        $this->assertSame('https://example.com/backend/x', BackendPath::resolve('https://example.com/backend/x'));
    }

    public function testLinksUnderTheInstalledPrefixAreStoredWithTheCanonicalOne(): void
    {
        $this->assertSame('/backend/news', BackendPath::store('/backend/news'));

        config('App')->backendPath = 'admin';

        $this->assertSame('/backend/news', BackendPath::store('/admin/news'));
        $this->assertSame('backend', BackendPath::store('admin'));
        $this->assertSame('/administration', BackendPath::store('/administration'));
        $this->assertSame('/backend/x', BackendPath::store('/private/x', 'private'));
    }

    public function testNotificationLinksFollowThePrefix(): void
    {
        config('App')->backendPath = 'intranet';

        $this->assertSame('intranet/news/read/7', (new NotificationMessage('t', '', 'backend/news/read/7'))->path());
        $this->assertSame('news/hello', (new NotificationMessage('t', '', '/news/hello'))->path());
        $this->assertNull((new NotificationMessage('t', '', 'https://evil.example/backend'))->path());
    }

    public function testValidation(): void
    {
        $this->assertNull(BackendPath::error('admin'));
        $this->assertNull(BackendPath::error('gestio-2'));
        $this->assertNotNull(BackendPath::error('ab-'));
        $this->assertNotNull(BackendPath::error('x'));
        $this->assertNotNull(BackendPath::error('Admin'));
        $this->assertNotNull(BackendPath::error('admin/x'));
        $this->assertNotNull(BackendPath::error(str_repeat('a', 31)));
        $this->assertNull(BackendPath::error(str_repeat('a', 30)));

        foreach (['api', 'install', 'progress', 'login', 'logout', 'register', 'auth', 'uploads', 'assets', 'themes', 'ical', 'ca', 'es', 'en'] as $reserved) {
            $this->assertNotNull(BackendPath::error($reserved), $reserved);
        }

        $this->assertNotNull(BackendPath::error('agenda', ['agenda']));
        $this->assertContains('news', BackendPath::routedSegments());
        $this->assertNotContains('backend', BackendPath::routedSegments());
    }

    public function testRewriteForAChangeOfPrefix(): void
    {
        $event = new BackendPathChanged('admin', 'intranet');

        $this->assertSame('/backend/news', $event->rewrite('/admin/news'));
        $this->assertSame('backend/x', $event->rewrite('backend/x'));
        $this->assertSame('/news', $event->rewrite('/news'));
        $this->assertSame(
            '{"links":[{"label":"N","url":"/backend/news"},{"menu":"admin"}]}',
            $event->rewriteJson('{"links":[{"label":"N","url":"/admin/news"},{"menu":"admin"}]}'),
        );
        $this->assertSame('{"a":"b"}', $event->rewriteJson('{"a":"b"}'));
        $this->assertSame('not json', $event->rewriteJson('not json'));
    }

    public function testEnvValueIsReplacedOrAppended(): void
    {
        $this->assertSame("A = 1\napp.backendPath = admin\n", EnvWriter::withValue("A = 1\n", 'app.backendPath', 'admin'));
        $this->assertSame("A = 1\napp.backendPath = admin\n# app.backendPath = backend\n", EnvWriter::withValue("A = 1\napp.backendPath = backend\n# app.backendPath = backend\n", 'app.backendPath', 'admin'));
    }
}
