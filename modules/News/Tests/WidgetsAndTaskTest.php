<?php

declare(strict_types=1);

namespace Modules\News\Tests;

use Modules\News\Tasks\ExpireNotices;

/**
 * Notices on the dashboard per audience (acceptance criterion 6), "mark as read" from the widget,
 * the Latest news widget and the ExpireNotices task.
 *
 * @internal
 */
final class WidgetsAndTaskTest extends NewsTestCase
{
    public function testNoticesReachTheDashboardOfTheirAudienceOnly(): void
    {
        $this->newsPost('Avís per a tothom', ['type' => 'notice', 'priority' => 'critical', 'pinned' => 1]);
        $this->newsPost('Avís per a editors', ['type' => 'notice', 'visibility' => 'groups'], ['editor']);
        $this->newsPost('Avís caducat', ['type' => 'notice', 'expires_at' => date('Y-m-d H:i:s', time() - 60)]);
        $this->newsPost('Un article', ['type' => 'article']);

        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $home = $this->body('GET', 'backend');

        $this->assertContains('news.notices', $this->cards($this->send('GET', 'backend')));
        $this->assertStringContainsString('Avís per a tothom', $home);
        $this->assertStringNotContainsString('Avís per a editors', $home);
        $this->assertStringNotContainsString('Avís caducat', $home);
        $this->assertStringContainsString('Un article', $home, 'Latest news.');

        $this->send('POST', 'logout');
        $this->createUser('edi@example.com', 'editor');
        $this->login('edi@example.com');
        $this->assertStringContainsString('Avís per a editors', $this->body('GET', 'backend'));
    }

    public function testMarkAsReadFromTheWidgetReturnsToTheDashboard(): void
    {
        $id = $this->newsPost('Avís', ['type' => 'notice']);
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->assertStringContainsString('backend/news/read/' . $this->code($id) . '/mark', $this->body('GET', 'backend'));
        $this->send('POST', 'backend/news/read/' . $this->code($id) . '/mark', ['return' => 'backend'])->assertRedirectTo(site_url('backend'));
        $this->assertStringNotContainsString('backend/news/read/' . $this->code($id) . '/mark', $this->body('GET', 'backend'));
    }

    public function testAUserWithoutTheReaderPermissionGetsNoNewsWidgets(): void
    {
        $this->newsPost('Avís', ['type' => 'notice']);
        $ana = $this->createUser('ana@example.com', 'user');
        db_connect()->table('group_permissions')->like('permission', 'news-read', 'after')->delete();
        $this->refreshMatrixForTests();
        $this->login('ana@example.com');

        $this->assertNotContains('news.notices', $this->cards($this->send('GET', 'backend')));
        $this->assertFalse($ana->can('news-read.view'));
    }

    public function testTheTaskReleasesScheduledPostsAndExpiresNotices(): void
    {
        $ana       = $this->createUser('ana@example.com', 'user');
        $scheduled = $this->newsPost('Programat', ['type' => 'notice', 'status' => 'scheduled', 'published_at' => date('Y-m-d H:i:s', time() - 60)]);
        $future    = $this->newsPost('Futur', ['status' => 'scheduled', 'published_at' => date('Y-m-d H:i:s', time() + 3600)]);
        $expired   = $this->newsPost('Caducat', ['type' => 'notice', 'pinned' => 1, 'expires_at' => date('Y-m-d H:i:s', time() - 60)]);

        $result = (new ExpireNotices())->run();

        $this->assertFalse($result->failed());
        $this->assertSame('published', $this->row($scheduled)['status']);
        $this->assertSame('scheduled', $this->row($future)['status']);
        $this->assertSame('expired', $this->row($expired)['status']);
        $this->assertSame('0', (string) $this->row($expired)['pinned']);
        $this->assertSame(1, $this->notificationsOf((int) $ana->id), 'The released private notice notifies once.');

        (new ExpireNotices())->run();
        $this->assertSame(1, $this->notificationsOf((int) $ana->id));
        $this->assertSame(1, db_connect()->table('activity_log')->where('module', 'News')->where('action', 'notices_updated')->countAllResults());
    }

    public function testAnEmptyWidgetDoesNotPickUpDataOfEarlierViews(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        auth()->login($ana);
        // A view rendered earlier in the request leaves `$message` behind (CodeIgniter shares view data).
        theme_component('empty_state', ['title' => 'Before', 'message' => 'Leaked text']);

        $html = (new \Modules\News\Widgets\LatestNews())->render(['limit' => 5]);

        $this->assertStringContainsString('No news yet.', $html);
        $this->assertStringNotContainsString('Leaked text', $html);
    }

    private function refreshMatrixForTests(): void
    {
        (new \Modules\Core\Cache\CoreCache())->invalidate('permissions');
        (new \Modules\Core\Permissions\ShieldMatrix())->write();
        (new \Modules\Core\Cache\CoreCache())->invalidate('menus');
        (new \Modules\Core\Cache\CoreCache())->invalidate('widgets');
    }
}
