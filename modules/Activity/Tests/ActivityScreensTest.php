<?php

declare(strict_types=1);

namespace Modules\Activity\Tests;

use Kpcrudy\Crud;
use Modules\Api\Services\PersonalTokens;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Activity\SecretMasker;

/**
 * The Activity screens and the entries written by the earlier sessions' actions: logins and
 * failed logins, 403s, CRUD writes with the right user and module, token changes; the full log
 * only for `activity-log.*`, "My activity" limited to the user's own entries, IP addresses only
 * with `activity-log.network`, and no way to edit or delete an entry.
 *
 * @internal
 */
final class ActivityScreensTest extends ApiTestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function entries(string $action): array
    {
        return db_connect()->table('activity_log')->where('action', $action)->orderBy('id')->get()->getResultArray();
    }

    private function entryId(string $action, ?int $userId = null): int
    {
        $builder = db_connect()->table('activity_log')->where('action', $action);

        if ($userId !== null) {
            $builder->where('user_id', $userId);
        }

        $row = $builder->orderBy('id', 'desc')->get(1)->getRowArray();
        $this->assertIsArray($row, $action);

        return (int) $row['id'];
    }

    /**
     * Refused by kpcrudy: "not found", or back to the list with "not allowed" (never a page).
     *
     * @param array<string, mixed> $params
     */
    private function assertRefused(string $method, string $path, array $params = []): void
    {
        $status = $this->send($method, $path, $params)->response()->getStatusCode();
        $this->assertContains($status, [302, 403, 404], "{$method} {$path}");
    }

    public function testLoginsAndFailedLoginsAreLogged(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');

        $this->login('ana@example.com', 'wrong-password');
        $failed = $this->entries('login_failed');
        $this->assertCount(1, $failed);
        $this->assertSame('security', $failed[0]['type']);
        $this->assertSame('ana@example.com', $failed[0]['summary']);
        $this->assertNull($failed[0]['user_id']);
        $this->assertSame('192.0.2.10', $failed[0]['ip_address']);
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($failed));

        $this->login('Correct-horse-battery', 'x');
        $this->assertSame('', $this->entries('login_failed')[1]['summary'], 'A password typed as the email is not stored.');

        $this->login('ana@example.com');
        $login = $this->entries('login');
        $this->assertCount(1, $login);
        $this->assertSame([(int) $ana->id, 'Auth', 'activity'], [(int) $login[0]['user_id'], $login[0]['module'], $login[0]['type']]);
    }

    public function testForbiddenRequestsAreSecurityEntries(): void
    {
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');

        $this->send('GET', 'backend/activity')->assertStatus(403);

        $entry = $this->entries('forbidden');
        $this->assertCount(1, $entry);
        $this->assertSame(['security', (int) $ana->id, 'activity-log', 'GET /backend/activity'], [$entry[0]['type'], (int) $entry[0]['user_id'], $entry[0]['resource'], $entry[0]['summary']]);
    }

    public function testCrudWritesAndTokenChangesHaveTheRightUserAndModule(): void
    {
        $boss  = $this->createUser('boss@example.com', 'admin');
        $ana   = $this->createUser('ana@example.com', 'user');
        $token = (new PersonalTokens())->create($ana, 'Phone', ['*'], 30);
        $this->login('boss@example.com');

        $created = $this->entries('token_created');
        $this->assertSame(['security', 'Api', 'Phone'], [$created[0]['type'], $created[0]['module'], $created[0]['summary']]);

        $this->send('POST', 'backend/api/tokens/delete/' . Crud::maskId($token['id'], 'auth_identities'));

        $delete = $this->entries('delete');
        $this->assertCount(1, $delete);
        $this->assertSame([(int) $boss->id, 'Api', 'api', (string) $token['id'], 'Phone'], [(int) $delete[0]['user_id'], $delete[0]['module'], $delete[0]['resource'], $delete[0]['record_id'], $delete[0]['summary']]);
        $changes = json_decode((string) $delete[0]['changes'], true);
        $this->assertSame(SecretMasker::MASK, $changes['before']['secret'], 'The token hash is masked.');

        $revoked = $this->entries('token_revoked');
        $this->assertSame((int) $boss->id, (int) $revoked[0]['user_id']);
    }

    public function testAdministratorsBrowseTheLogWithNetworkFields(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->createUser('ana@example.com', 'user');
        $this->login('ana@example.com');
        $this->login('boss@example.com');

        $list = $this->send('GET', 'backend/activity');
        $list->assertOK();
        $list->assertSee('Signed in');
        $list->assertSee('ana');

        $show = $this->send('GET', 'backend/activity/show/' . Crud::maskId($this->entryId('login'), 'activity_log'));
        $show->assertOK();
        $show->assertSee('192.0.2.10');
    }

    public function testMyActivityShowsOnlyOwnEntriesWithoutNetworkFields(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $ana = $this->createUser('ana@example.com', 'user');
        $this->login('boss@example.com');
        $bossEntry = $this->entryId('login');
        $this->login('ana@example.com');
        $anaEntry = $this->entryId('login', (int) $ana->id);

        $list = $this->send('GET', 'backend/activity/mine');
        $list->assertOK();
        $list->assertSee(lang('Activity.mineTitle'));

        $mine = $this->send('GET', 'backend/activity/mine/show/' . Crud::maskId($anaEntry, 'activity_log'));
        $mine->assertOK();
        $mine->assertDontSee('192.0.2.10');

        $other = $this->send('GET', 'backend/activity/mine/show/' . Crud::maskId($bossEntry, 'activity_log'));
        $this->assertContains($other->response()->getStatusCode(), [302, 404], 'Another user\'s entry is "not found".');
        $other->assertDontSee('boss@example.com');
        $this->send('GET', 'backend/activity/show/' . Crud::maskId($bossEntry, 'activity_log'))->assertStatus(403);
        $export = $this->send('GET', 'backend/activity/mine/export');
        $this->assertStringNotContainsString('attachment', $export->response()->getHeaderLine('Content-Disposition'));
    }

    public function testTheLogIsAppendOnly(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $id     = $this->entryId('login');
        $masked = Crud::maskId($id, 'activity_log');
        $count  = db_connect()->table('activity_log')->countAllResults();

        $this->assertRefused('GET', 'backend/activity/edit/' . $masked);
        $this->assertRefused('GET', 'backend/activity/add');
        $this->assertRefused('POST', 'backend/activity/edit/' . $masked, ['summary' => 'forged']);
        $this->assertRefused('POST', 'backend/activity/delete/' . $masked);
        $this->assertRefused('POST', 'backend/activity/purge/' . $masked);
        $this->send('POST', 'backend/activity', ['bulk_action' => 'delete', 'ids' => [$masked]]);
        $this->assertRefused('POST', 'backend/activity/mine/delete/' . $masked);

        $row = db_connect()->table('activity_log')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('boss@example.com', $row['summary']);
        $this->assertGreaterThanOrEqual($count, db_connect()->table('activity_log')->where('action !=', 'forbidden')->countAllResults());
        $this->assertSame(1, db_connect()->table('activity_log')->where('id', $id)->countAllResults());

        foreach (service('routes')->getRoutes('delete') + service('routes')->getRoutes('put') + service('routes')->getRoutes('patch') as $from => $to) {
            $this->assertStringNotContainsString('backend/activity', (string) $from);
        }
    }

    public function testExportIsLoggedAndOnlyForExporters(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $export = $this->send('GET', 'backend/activity/export');
        $export->assertOK();
        $this->assertStringContainsString('attachment', $export->response()->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('boss@example.com', (string) $export->response()->getBody());
        $this->assertCount(1, $this->entries('export'));
        $this->assertSame('Activity', $this->entries('export')[0]['module']);
    }
}
