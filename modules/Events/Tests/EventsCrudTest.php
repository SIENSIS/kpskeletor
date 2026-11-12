<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use Modules\Events\Services\AssignableGroups;

/**
 * The administration of the events: the assignable-groups rule (an editor cannot target
 * forbidden groups, also with forged posts), the list scope with and without
 * `events-events.all`, forged requests of read-only users, duplicate, the sanitised description
 * and the audience saved to `event_audience`.
 *
 * @internal
 */
final class EventsCrudTest extends EventsTestCase
{
    public function testAnEditorCannotTargetGroupsTheyMayNotAssign(): void
    {
        $editor = $this->createUser('edi@example.com', 'editor');
        $ids    = (new AssignableGroups())->ids($editor);

        $this->assertContains($this->groupId('editor'), $ids, 'Their own group.');
        $this->assertContains($this->groupId('user'), $ids, 'A group whose permissions they hold.');
        $this->assertNotContains($this->groupId('superadmin'), $ids);
        $this->assertNotContains($this->groupId('admin'), $ids, 'Admins hold permissions editors do not.');

        $this->login('edi@example.com');
        $form = $this->body('GET', 'backend/events/add');
        $this->assertMatchesRegularExpression('/<option value="' . $this->groupId('editor') . '"[^>]*>Editor/', $form);
        $this->assertDoesNotMatchRegularExpression('/<option value="' . $this->groupId('superadmin') . '"[^>]*>Superadmin/', $form, 'Only assignable groups are offered.');

        foreach (['superadmin', 'admin'] as $group) {
            $page = $this->body('POST', 'backend/events/add', $this->form('Només per a ' . $group, ['groups' => [(string) $this->groupId($group)]]));

            $this->assertSame(0, db_connect()->table('event_translations')->where('title', 'Només per a ' . $group)->countAllResults(), $group);
            $this->assertStringContainsString(lang('Events.errors.forbiddenGroups'), $page, $group);
        }

        $this->assertSame(0, db_connect()->table('event_audience')->countAllResults());
        $this->assertGreaterThan(0, db_connect()->table('activity_log')->where('module', 'Events')->where('action', 'forbidden_audience')->countAllResults(), 'Logged as a security event.');

        // A forbidden group mixed with an allowed one, and a forged non-numeric value.
        $this->send('POST', 'backend/events/add', $this->form('Barreja', ['groups' => [(string) $this->groupId('editor'), (string) $this->groupId('superadmin')]]));
        $this->send('POST', 'backend/events/add', $this->form('Forjada', ['groups' => ['1 OR 1=1']]));
        $this->assertSame(0, db_connect()->table('events')->countAllResults());
    }

    public function testAnEditorSavesAnAudienceOfAllowedGroupsAndUsers(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('edi@example.com', 'editor');
        $this->login('edi@example.com');

        $this->send('POST', 'backend/events/add', $this->form('Editors i John', [
            'groups' => [(string) $this->groupId('editor')],
            'users'  => [(string) $john->id],
        ]))->assertRedirect();

        $event = db_connect()->table('event_translations')->where('title', 'Editors i John')->get()->getRowArray();
        $this->assertIsArray($event, 'Saved: ' . ($_SESSION['error'] ?? ''));
        $id       = (int) $event['event_id'];
        $audience = db_connect()->table('event_audience')->where('event_id', $id)->orderBy('id')->get()->getResultArray();

        $this->assertCount(2, $audience);
        $this->assertSame([$this->groupId('editor'), null], [(int) $audience[0]['group_id'], $audience[0]['user_id']]);
        $this->assertSame([null, (int) $john->id], [$audience[1]['group_id'], (int) $audience[1]['user_id']]);
        $this->assertSame((int) db_connect()->table('users')->where('username', 'edi')->get()->getRow()->id, (int) $this->row($id)['created_by']);
        $this->assertSame('2026-12-01 10:00:00', $this->row($id)['starts_at']);

        // Editing the groups keeps the user rows.
        $this->send('POST', 'backend/events/edit/' . $this->code($id), $this->form('Editors i John', [
            'groups' => [(string) $this->groupId('user')],
            'users'  => [(string) $john->id],
        ]))->assertRedirect();
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $id)->where('group_id', $this->groupId('user'))->countAllResults());
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $id)->where('user_id', (int) $john->id)->countAllResults());
        $this->assertSame(0, db_connect()->table('event_audience')->where('event_id', $id)->where('group_id', $this->groupId('editor'))->countAllResults());
        $this->assertSame('1', (string) $this->row($id)['sequence'], 'Every change bumps the iCal sequence.');

        // Not restricted any more: the audience is cleared.
        $this->send('POST', 'backend/events/edit/' . $this->code($id), $this->form('Editors i John', ['visibility' => 'private']))->assertRedirect();
        $this->assertSame(0, db_connect()->table('event_audience')->where('event_id', $id)->countAllResults());
    }

    public function testAGroupSetByAnAdminIsKeptWhenAnEditorEditsTheEvent(): void
    {
        $this->createUser('edi@example.com', 'editor');
        $id = $this->event('Per als admins i editors', ['visibility' => 'restricted'], ['admin', 'editor']);
        $this->login('edi@example.com');

        $this->send('POST', 'backend/events/edit/' . $this->code($id), $this->form('Per als admins i editors', [
            'groups' => [(string) $this->groupId('editor'), (string) $this->groupId('user')],
        ]))->assertRedirect();

        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $id)->where('group_id', $this->groupId('admin'))->countAllResults(), 'The admin group is kept.');
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $id)->where('group_id', $this->groupId('user'))->countAllResults(), 'The editor added a group they may assign.');

        // Removing themselves from the audience would leave the event outside their scope: refused.
        $this->send('POST', 'backend/events/edit/' . $this->code($id), $this->form('Per als admins i editors', ['groups' => [(string) $this->groupId('user')]]));
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $id)->where('group_id', $this->groupId('editor'))->countAllResults(), 'Rolled back.');
    }

    public function testARestrictedEventNeedsAnAudience(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $page = $this->body('POST', 'backend/events/add', $this->form('Sense públic', ['groups' => [], 'users' => []]));

        $this->assertSame(0, db_connect()->table('events')->countAllResults());
        $this->assertStringContainsString(lang('Events.errors.emptyAudience'), $page);
    }

    public function testTheListIsLimitedToTheUsersAudienceWithoutTheAllPermission(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('edi@example.com', 'editor');
        $this->event('Privada');
        $onlyJohn = $this->event('Només per al John', ['visibility' => 'restricted'], [], [(int) $john->id]);
        $admins   = $this->event('Només admins', ['visibility' => 'restricted'], ['admin']);
        $this->login('edi@example.com');
        $own = $this->event('Creada per l’editor', ['visibility' => 'restricted', 'created_by' => $this->loggedInUserId()], ['admin']);

        $list = $this->body('GET', 'backend/events');
        $this->assertStringContainsString('Privada', $list);
        $this->assertStringContainsString('Creada per l’editor', $list, 'Events they created.');
        $this->assertStringNotContainsString('Només per al John', $list);
        $this->assertStringNotContainsString('Només admins', $list);

        // Outside the scope: "not found" (kpcrudy) for every operation.
        foreach ([['GET', 'show/'], ['GET', 'edit/'], ['POST', 'edit/'], ['POST', 'delete/'], ['POST', 'duplicate/']] as [$method, $operation]) {
            try {
                $response = $this->send($method, 'backend/events/' . $operation . $this->code($onlyJohn), $method === 'POST' ? $this->form('Presa') : []);
                $this->assertTrue($response->isRedirect() || $response->response()->getStatusCode() === 404, "{$method} {$operation}");
            } catch (PageNotFoundException) {
                // kpcrudy answers records outside the scope with a 404.
            }

            unset($_SESSION['error'], $_SESSION['success']);
        }

        $this->assertSame('Només per al John', db_connect()->table('event_translations')->where('event_id', $onlyJohn)->get()->getRow()->title);
        $this->assertNull($this->row($onlyJohn)['deleted_at']);
        $this->assertSame(4, db_connect()->table('events')->countAllResults(), 'Nothing duplicated.');
        $this->assertSame(1, $admins > 0 && $own > 0 ? 1 : 0);
    }

    public function testTheAllPermissionShowsEveryEventWithAVisibleToMeFilter(): void
    {
        $john = $this->createUser('john@example.com', 'user');
        $this->createUser('boss@example.com', 'admin');
        $this->event('Només per al John', ['visibility' => 'restricted'], [], [(int) $john->id]);
        $this->event('Privada');
        $this->login('boss@example.com');

        $all = $this->body('GET', 'backend/events');
        $this->assertStringContainsString('Només per al John', $all, 'events-events.all sees every event.');
        $this->assertStringContainsString('Privada', $all);

        $mine = $this->body('GET', 'backend/events?f[scope]=mine');
        $this->assertStringNotContainsString('Només per al John', $mine);
        $this->assertStringContainsString('Privada', $mine);

        // The calendar is still limited to the audience, administrators included.
        $this->assertNotContains('Només per al John', $this->feedTitles());
    }

    public function testForgedPostsOfAReadOnlyUserChangeNothing(): void
    {
        $id      = $this->event('Intacta');
        $trashed = $this->event('A la paperera', ['deleted_at' => '2026-01-01 10:00:00']);
        $this->loginWith('events-events.list', 'events-events.show');
        $before = $this->snapshot();

        $list = $this->body('GET', 'backend/events');
        $this->assertStringContainsString('Intacta', $list);

        foreach (['add', 'edit/', 'duplicate/', 'delete/', 'export', 'import'] as $operation) {
            $this->assertStringNotContainsString('backend/events/' . $operation, $list, $operation);
        }

        foreach ([
            ['POST', 'add', $this->form('Forjada')],
            ['POST', 'edit/' . $this->code($id), $this->form('Canviada')],
            ['POST', 'delete/' . $this->code($id), []],
            ['POST', 'duplicate/' . $this->code($id), []],
            ['POST', 'restore/' . $this->code($trashed), []],
            ['POST', 'purge/' . $this->code($trashed), []],
            ['POST', 'bulk', ['action' => 'delete', 'ids' => [$this->code($id)]]],
            ['GET', 'export', []],
            ['GET', 'import', []],
            ['GET', 'trash', []],
        ] as [$method, $operation, $params]) {
            try {
                $response = $this->send($method, 'backend/events/' . $operation, $params);
            } catch (PageNotFoundException $e) {
                $this->assertSame(lang('Kpcrudy.notAllowed'), $e->getMessage(), "{$method} {$operation}");

                continue;
            }

            $this->assertCrudRefused($response, 'backend/events', "{$method} {$operation}");
            unset($_SESSION['error'], $_SESSION['success']);
        }

        $this->assertSame($before, $this->snapshot(), 'Nothing changed.');

        $this->send('POST', 'logout');
        $this->createUser('nobody@example.com', 'user');
        $this->login('nobody@example.com');
        $this->send('GET', 'backend/events')->assertStatus(403);
        $this->send('POST', 'backend/events/add', $this->form('Sense permís'))->assertStatus(403);
        $this->send('GET', 'backend/events/categories')->assertStatus(403);
        $this->assertSame($before, $this->snapshot());
    }

    public function testDuplicateCopiesTextsAndAudience(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $id = $this->event('Original', ['visibility' => 'restricted', 'location' => 'Aula 1'], ['editor']);
        $this->login('boss@example.com');

        $response = $this->send('POST', 'backend/events/duplicate/' . $this->code($id));
        $this->assertTrue($response->isRedirect());

        $copy = db_connect()->table('events')->where('id !=', $id)->get()->getRowArray();
        $this->assertIsArray($copy);
        $this->assertStringContainsString('/backend/events/edit/', $response->response()->getHeaderLine('Location'));
        $this->assertSame('Aula 1', $copy['location']);
        $this->assertSame(lang('Events.copyOf', ['Original']), db_connect()->table('event_translations')->where('event_id', $copy['id'])->get()->getRow()->title);
        $this->assertSame(1, db_connect()->table('event_audience')->where('event_id', $copy['id'])->where('group_id', $this->groupId('editor'))->countAllResults());
        $this->assertSame(1, db_connect()->table('activity_log')->where('module', 'Events')->where('action', 'duplicate')->countAllResults(), 'Logged by the Core.');
    }

    public function testTheDescriptionIsSanitisedAndRecurrenceNormalised(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/events/add', $this->form('Setmanal', [
            'kp_tr'            => ['ca' => ['title' => 'Setmanal', 'description' => '<p onclick="alert(1)">Hola<script>alert(2)</script> <strong>bé</strong></p>']],
            'visibility'       => 'private',
            'recur_freq'       => 'weekly',
            'recur_days'       => ['mo', 'we', 'xx'],
            'recur_interval'   => '2',
            'recur_exceptions' => "2026-12-09, nope\n2026-12-02",
        ]))->assertRedirect();

        $text = db_connect()->table('event_translations')->where('title', 'Setmanal')->get()->getRowArray();
        $this->assertIsArray($text, (string) ($_SESSION['error'] ?? ''));
        $this->assertStringNotContainsString('script', (string) $text['description']);
        $this->assertStringNotContainsString('onclick', (string) $text['description']);
        $this->assertStringContainsString('<strong>bé</strong>', (string) $text['description']);

        $row = $this->row((int) $text['event_id']);
        $this->assertSame('weekly', $row['recur_freq']);
        $this->assertSame('mo,we', $row['recur_days']);
        $this->assertSame('2', (string) $row['recur_interval']);
        $this->assertSame("2026-12-02\n2026-12-09", $row['recur_exceptions']);

        // The end cannot be before the start.
        $this->send('POST', 'backend/events/add', $this->form('Al revés', ['visibility' => 'private', 'ends_at' => '2026-11-30T10:00']));
        $this->assertSame(0, db_connect()->table('event_translations')->where('title', 'Al revés')->countAllResults());
    }

    public function testCategoriesGetASlugAndASafeColour(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $this->send('POST', 'backend/events/categories/add', [
            'kp_tr' => ['ca' => ['name' => 'Sortides Escolars', 'description' => '']], 'color' => '#AABBCC', 'position' => '1', 'active' => '1',
        ])->assertRedirect();

        $row = db_connect()->table('event_categories')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame('sortides-escolars', $row['slug']);
        $this->assertSame('#aabbcc', $row['color']);
    }

    /**
     * A complete event form: restricted by default (callers give the audience), 1 December 2026.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function form(string $title, array $values = []): array
    {
        return $values + [
            'kp_tr'      => ['ca' => ['title' => $title, 'description' => '']],
            'starts_at'  => '2026-12-01T10:00',
            'ends_at'    => '2026-12-01T11:00',
            'all_day'    => '0',
            'status'     => 'confirmed',
            'visibility' => 'restricted',
            'recur_interval' => '1',
        ];
    }

    /**
     * Every row of the Events tables, to prove forged requests changed nothing.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $tables = [];

        foreach (['events', 'event_translations', 'event_audience', 'event_categories'] as $table) {
            $tables[$table] = db_connect()->table($table)->orderBy('id')->get()->getResultArray();
        }

        return $tables;
    }
}
