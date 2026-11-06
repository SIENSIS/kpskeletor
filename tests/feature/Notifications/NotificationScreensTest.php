<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Kpcrudy\Crud;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Permissions\GrantManager;
use Tests\Support\Scheduler\DeclaresFixtures;
use Tests\Support\Scheduler\MandatoryNotice;
use Tests\Support\Scheduler\TestNotice;

/**
 * The notification screens (resource core-notifications, every group): the bell in the layout
 * and its JSON, "open", "mark all as read", the list (own records only) and the preferences.
 *
 * @internal
 */
final class NotificationScreensTest extends AuthTestCase
{
    use DeclaresFixtures;

    private int $mine = 0;

    private int $other = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->declareFixtures([['Core', 'notification', TestNotice::class], ['Core', 'notification', MandatoryNotice::class]]);

        $ana = $this->createUser('ana@example.com', 'user');
        $bob = $this->createUser('bob@example.com', 'user');

        notify($ana, new TestNotice(['name' => 'Mine', 'url' => 'backend/profile']));
        notify($ana, new TestNotice(['name' => 'Second']));
        notify($bob, new TestNotice(['name' => 'Bobs secret']));
        $this->other = (int) db_connect()->table('notifications')->where('user_id', $bob->id)->get()->getRowArray()['id'];
        $this->mine  = (int) db_connect()->table('notifications')->where('user_id', $ana->id)->orderBy('id')->get()->getRowArray()['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(int $id): array
    {
        $row = db_connect()->table('notifications')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    public function testGuestsAreSentToTheLogin(): void
    {
        $this->send('GET', 'backend/notifications/bell')->assertRedirect();
        $this->send('POST', 'backend/notifications/read-all')->assertRedirect();
        $this->assertNull($this->row($this->mine)['read_at']);
    }

    public function testTheLayoutShowsTheBellWithTheUnreadCount(): void
    {
        $this->login('ana@example.com');
        $response = $this->send('GET', 'backend/profile');

        $response->assertOK();
        $response->assertSee('data-component="core/ui/bell"', null);
        $this->assertMatchesRegularExpression('#data-bell-count>2</span>#', (string) $response->response()->getBody());
    }

    public function testTheBellListsOnlyTheUsersOwnNotifications(): void
    {
        $this->login('ana@example.com');
        $response = $this->send('GET', 'backend/notifications/bell');

        $response->assertOK();
        $data = json_decode((string) $response->response()->getBody(), true);
        $this->assertSame(2, $data['unread']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('The scheduled task Second failed', $data['items'][0]['title'], 'Newest first.');
        $this->assertStringNotContainsString('Bobs secret', (string) $response->response()->getBody());
        $this->assertSame(site_url('backend/notifications/open/' . $this->mine), $data['items'][1]['url']);
    }

    public function testOpeningMarksAsReadAndGoesToTheLink(): void
    {
        $this->login('ana@example.com');

        $this->send('GET', 'backend/notifications/open/' . $this->mine)->assertRedirectTo(site_url('backend/profile'));
        $this->assertNotNull($this->row($this->mine)['read_at']);
    }

    public function testSomeoneElsesNotificationCannotBeOpenedOrSeen(): void
    {
        $this->login('ana@example.com');

        $this->send('GET', 'backend/notifications/open/' . $this->other)->assertRedirectTo(site_url('backend/notifications'));
        $this->assertNull($this->row($this->other)['read_at']);

        $list = $this->send('GET', 'backend/notifications');
        $list->assertOK();
        $list->assertSee('The scheduled task Mine failed');
        $list->assertDontSee('Bobs secret');

        $show = $this->send('GET', 'backend/notifications/show/' . Crud::idForUrl($this->other, 'notifications'));
        $show->assertDontSee('Bobs secret');
    }

    public function testMarkAllAsReadOnlyTouchesTheUsersNotifications(): void
    {
        $this->login('ana@example.com');

        $this->send('POST', 'backend/notifications/read-all')->assertRedirectTo(site_url('backend/notifications'));

        $this->assertSame(0, db_connect()->table('notifications')->where('user_id !=', $this->row($this->other)['user_id'])->where('read_at', null)->countAllResults());
        $this->assertNull($this->row($this->other)['read_at']);
    }

    public function testMarkAllAsReadFromTheBellAnswersJson(): void
    {
        $this->login('ana@example.com');
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
        $response = $this->send('POST', 'backend/notifications/read-all');
        $this->withHeaders([]);

        $response->assertOK();
        $this->assertSame(['marked' => 2, 'unread' => 0], json_decode((string) $response->response()->getBody(), true));
    }

    public function testPreferencesCanBeChangedExceptMandatoryTypes(): void
    {
        $this->login('ana@example.com');
        $ana = (int) $this->row($this->mine)['user_id'];

        $page = $this->send('GET', 'backend/profile/notifications');
        $page->assertOK();
        $page->assertSee('Test notice');
        $page->assertSee('Mandatory notice');

        $this->send('POST', 'backend/profile/notifications', [
            'types'    => [TestNotice::class, MandatoryNotice::class],
            'channels' => [TestNotice::class => ['database'], MandatoryNotice::class => []],
        ])->assertRedirectTo(site_url('backend/profile/notifications'));

        $this->assertSame(['database'], service('notifications')->channelsFor($ana, TestNotice::class));
        $this->assertSame(['database'], service('notifications')->channelsFor($ana, MandatoryNotice::class));
        $this->assertSame(0, db_connect()->table('notification_preferences')->where('type', MandatoryNotice::class)->countAllResults());
    }

    public function testUsersWithoutThePermissionAreRefused(): void
    {
        (new GrantManager())->setGroupGrants(null, 'user', []);
        $this->syncPermissions();
        $this->login('ana@example.com');

        $this->send('GET', 'backend/notifications/bell')->assertStatus(403);
        $this->send('GET', 'backend/profile/notifications')->assertStatus(403);
        $this->send('POST', 'backend/profile/notifications', ['types' => [TestNotice::class]])->assertStatus(403);
        $this->send('POST', 'backend/notifications/read-all')->assertStatus(403);
        $this->assertNull($this->row($this->mine)['read_at']);
    }
}
