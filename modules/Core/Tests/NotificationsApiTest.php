<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Kpcrudy\Crud;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Notifications\Types\ScheduledTaskFailed;
use Modules\Core\Permissions\CrudPermissions;

/**
 * API resource `notifications` (D-26-01): only the user's own notifications, rendered in the
 * request's language, unread count, mark one or all as read, scopes and read-only otherwise.
 *
 * @internal
 */
final class NotificationsApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
    }

    public function testTheListHasOnlyTheUsersNotificationsInTheRequestLanguage(): void
    {
        $ana  = $this->createUser('ana@example.com', 'user');
        $joan = $this->createUser('joan@example.com', 'user');
        $this->notification($ana, 'Backup');
        $this->notification($joan, 'Other');

        $catalan = $this->json($this->api('GET', 'api/v1/notifications', null, $this->tokenFor($ana), ['Accept-Language' => 'ca']))['data'];
        $this->assertSame('Ha fallat la tasca programada Backup', $catalan[0]['title']);

        $response = $this->api('GET', 'api/v1/notifications', null, $this->tokenFor($ana), ['Accept-Language' => 'en']);
        $response->assertStatus(200);
        $data = $this->json($response)['data'];

        $this->assertCount(1, $data);
        $this->assertSame('The scheduled task Backup failed', $data[0]['title']);
        $this->assertSame('ScheduledTaskFailed', $data[0]['type']);
        $this->assertSame('Core', $data[0]['module']);
        $this->assertFalse($data[0]['read']);
        $this->assertStringEndsWith('/backend/schedule', (string) $data[0]['url']);
        $this->assertArrayNotHasKey('data', $data[0]);
        $this->assertArrayNotHasKey('user_id', $data[0]);
    }

    public function testSomeoneElsesNotificationIsNotFound(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $joan  = $this->createUser('joan@example.com', 'user');
        $other = $this->notification($joan, 'Other');
        $token = $this->tokenFor($ana);

        $this->api('GET', 'api/v1/notifications/' . Crud::maskId($other, 'notifications'), null, $token)->assertStatus(404);
        $this->api('POST', 'api/v1/notifications/' . Crud::maskId($other, 'notifications') . '/read', null, $token)->assertStatus(404);
        $this->assertNull(db_connect()->table('notifications')->where('id', $other)->get()->getRowArray()['read_at']);
    }

    public function testUnreadCountAndMarkingAsRead(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $joan  = $this->createUser('joan@example.com', 'user');
        $first = $this->notification($ana, 'A');
        $this->notification($ana, 'B');
        $this->notification($ana, 'C');
        $this->notification($joan, 'D');
        $token = $this->tokenFor($ana);

        $this->assertSame(['unread' => 3], $this->json($this->api('GET', 'api/v1/notifications/unread', null, $token))['data']);

        $read = $this->api('POST', 'api/v1/notifications/' . Crud::maskId($first, 'notifications') . '/read', null, $token);
        $read->assertStatus(200);
        $this->assertTrue($this->json($read)['data']['marked']);
        $this->assertSame(['unread' => 2], $this->json($this->api('GET', 'api/v1/notifications/unread', null, $token))['data']);

        $all = $this->api('POST', 'api/v1/notifications/read-all', null, $token);
        $all->assertStatus(200);
        $this->assertSame(['marked' => 2], $this->json($all)['data']);
        $this->assertSame(['unread' => 0], $this->json($this->api('GET', 'api/v1/notifications/unread', null, $token))['data']);

        // Joan's notification is untouched.
        $this->assertSame(1, db_connect()->table('notifications')->where('read_at', null)->countAllResults());
    }

    public function testATokenLimitedToListingCannotMarkAsRead(): void
    {
        $ana   = $this->createUser('ana@example.com', 'user');
        $id    = $this->notification($ana, 'A');
        $token = $this->tokenFor($ana, ['core-notifications.list']);

        $this->api('GET', 'api/v1/notifications', null, $token)->assertStatus(200);
        $this->api('GET', 'api/v1/notifications/unread', null, $token)->assertStatus(200);
        $this->api('POST', 'api/v1/notifications/' . Crud::maskId($id, 'notifications') . '/read', null, $token)->assertStatus(403);
        $this->api('POST', 'api/v1/notifications/read-all', null, $token)->assertStatus(403);
        $this->assertContains($this->api('DELETE', 'api/v1/notifications/' . Crud::maskId($id, 'notifications'), null, $this->tokenFor($ana))->response()->getStatusCode(), [403, 405]);
        $this->assertSame(1, db_connect()->table('notifications')->where('read_at', null)->countAllResults());
        $this->api('GET', 'api/v1/notifications', null, $this->tokenFor($ana, ['news-api.list']))->assertStatus(403);
    }

    private function notification(User $user, string $task): int
    {
        $db = db_connect();
        $db->table('notifications')->insert([
            'user_id' => (int) $user->id, 'module' => 'Core', 'type' => ScheduledTaskFailed::class,
            'data' => json_encode(['class' => 'App\\Tasks\\' . $task, 'output' => 'Error']), 'read_at' => null,
            'created_at' => Time::now()->toDateTimeString(),
        ]);

        return (int) $db->insertID();
    }
}
