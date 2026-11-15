<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Modules\Core\Calendar\CalendarEvent;
use Modules\Core\Groups\GroupRegistry;
use Modules\Core\Permissions\GrantManager;
use Modules\Users\Calendar\AdminBirthdaysProvider;
use Modules\Users\Calendar\BirthdaysProvider;
use Modules\Users\Services\BirthdayAudiences;

/**
 * The birthdays calendars (D-25-07, D-25-08), with the example of a school: `admin` is the
 * management (sees every group), `editor` the teachers (see the teachers), `user` the students
 * (no permission to see birthdays at all).
 *
 * - the shared calendar needs the setting, the person's consent, the viewer's permission and the
 *   groups table; only the day and month; 29 February; stable keys;
 * - the administrative calendar shows every date of birth to holders of `users-users.birthdate`;
 * - the date of birth in the user's record, and the Groups field that edits the table.
 *
 * @internal
 */
final class BirthdaysProviderTest extends UsersTestCase
{
    private User $management;

    private User $teacher;

    private User $colleague;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        setting('Users.birthdaysCalendar', true);

        $this->management = $this->createUser('direccio@example.com', 'admin');
        $this->teacher    = $this->createUser('profe@example.com', 'editor');
        $this->colleague  = $this->createUser('profe2@example.com', 'editor');
        $this->student    = $this->createUser('alumne@example.com', 'user');

        $this->profile($this->management, '1970-05-10', true, 'Directora');
        $this->profile($this->teacher, '1980-05-17', true, 'Profe A');
        $this->profile($this->colleague, '1982-05-20', false, 'Profe B');
        $this->profile($this->student, '2010-05-25', true, 'Alumne B');

        $db = db_connect();
        $db->table('group_birthday_audiences')->truncate();

        foreach ([['admin', 'admin'], ['admin', 'editor'], ['admin', 'user'], ['editor', 'editor'], ['user', 'user']] as [$group, $target]) {
            $db->table('group_birthday_audiences')->insert(['group_id' => $this->groupId($group), 'target_group_id' => $this->groupId($target)]);
        }

        // Students may not see birthdays at all (the school's choice).
        $grants = array_values(array_diff(array_column($db->table('group_permissions')->where('group', 'user')->get()->getResultArray(), 'permission'), ['users-birthdays.view']));
        (new GrantManager())->setGroupGrants(null, 'user', $grants);
    }

    public function testItIsOffWithoutTheSetting(): void
    {
        setting('Users.birthdaysCalendar', false);

        $this->assertSame([], $this->names($this->management));
    }

    public function testTeachersSeeTheTeachersWhoShareTheirBirthday(): void
    {
        $this->assertSame(['Profe A'], $this->names($this->colleague), 'Profe B did not consent; the students and the management are not in the teachers\' row.');
        $this->assertSame(['Profe A'], $this->names($this->teacher), 'Their own shared birthday too.');
    }

    public function testManagementSeesEveryGroupButOnlySharedBirthdays(): void
    {
        $this->assertSame(['Alumne B', 'Directora', 'Profe A'], $this->names($this->management));
    }

    public function testStudentsWithoutThePermissionSeeNothing(): void
    {
        $this->assertSame([], $this->names($this->student), 'Even with their group\'s row in the table.');
    }

    public function testTheAdministrativeCalendarHasEveryDateOfBirth(): void
    {
        $events = $this->events(new AdminBirthdaysProvider(), $this->management, '2027-05-01', '2027-06-01');
        $this->assertSame(['Alumne B', 'Directora', 'Profe A', 'Profe B'], $this->titles($events), 'Consent is not needed for administrative work.');
        $this->assertSame('users.birthdaysadmin', $events[0]->calendar);

        $this->assertSame([], $this->events(new AdminBirthdaysProvider(), $this->teacher, '2027-05-01', '2027-06-01'), 'Only with users-users.birthdate.');
    }

    public function testBirthdaysAreAllDayEventsWithoutTheYear(): void
    {
        $events = $this->events(new BirthdaysProvider(), $this->teacher, '2027-05-01', '2027-06-01');

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->allDay);
        $this->assertSame('2027-05-17', $events[0]->start->format('Y-m-d'));
        $this->assertSame('users.birthdays', $events[0]->calendar);
        $this->assertSame('user-' . $this->teacher->id . '-2027', $events[0]->key);
        $this->assertSame('Aniversari de Profe A', $events[0]->titleIn('ca'));
        $this->assertStringNotContainsString('1980', $events[0]->titleIn('en'), 'Never the year or the age.');
    }

    public function testTheTwentyNinthOfFebruaryAndRangesOverNewYear(): void
    {
        $this->profile($this->teacher, '2000-02-29', true, 'Profe A');
        $this->profile($this->colleague, '1999-01-03', true, 'Profe B');
        $dates = fn (string $from, string $to): array => array_map(static fn (CalendarEvent $e): string => $e->start->format('Y-m-d'), $this->events(new BirthdaysProvider(), $this->teacher, $from, $to));

        $this->assertSame(['2027-02-28'], $dates('2027-02-01', '2027-03-01'));
        $this->assertSame(['2028-02-29'], $dates('2028-02-01', '2028-03-01'));
        $this->assertSame(['2028-01-03'], $dates('2027-12-15', '2028-01-15'));
    }

    public function testTheRecordShowsTheDateOfBirthOnlyWithThePermission(): void
    {
        $this->login('direccio@example.com');
        $page = (string) $this->send('GET', 'backend/users/show/' . $this->code($this->colleague->id))->response()->getBody();
        $this->assertStringContainsString('Date of birth', $page);
        $this->assertStringContainsString('Not shared', $page);
        $this->send('POST', 'logout');

        (new GrantManager())->setGroupGrants(null, 'editor', ['users-users.list', 'users-users.show', 'users-birthdays.view', 'auth-profile.*', 'dashboard.view']);
        $this->login('profe@example.com');
        $page = (string) $this->send('GET', 'backend/users/show/' . $this->code($this->colleague->id))->response()->getBody();
        $this->assertStringNotContainsString('Date of birth', $page);
    }

    public function testTheGroupsFormEditsWhoseBirthdaysAGroupSees(): void
    {
        $this->login('direccio@example.com');
        $editor = $this->groupId('editor');

        $this->send('POST', 'backend/users/groups/edit/' . $this->code($editor, 'groups'), [
            'title' => 'Editor', 'description' => '', 'two_factor' => 'optional',
            'birthday_groups' => [(string) $this->groupId('editor'), (string) $this->groupId('admin')],
        ]);

        $targets = array_map('intval', array_column(db_connect()->table('group_birthday_audiences')->where('group_id', $editor)->get()->getResultArray(), 'target_group_id'));
        sort($targets);
        $expected = [$this->groupId('admin'), $this->groupId('editor')];
        sort($expected);
        $this->assertSame($expected, $targets);
        $this->assertSame(['Directora', 'Profe A'], $this->names($this->colleague));
    }

    public function testDefaultsAreWrittenWhenTheReservedGroupsAreCreatedAndNeverOverwritten(): void
    {
        $db = db_connect();
        $db->table('group_birthday_audiences')->truncate();

        Events::trigger(GroupRegistry::EVENT_RESERVED_CREATED, ['superadmin', 'admin', 'editor', 'user']);
        $this->assertSame(7, $db->table('group_birthday_audiences')->countAllResults(), 'Each group sees itself, admin sees the four.');
        $this->assertSame(4, $db->table('group_birthday_audiences')->where('group_id', $this->groupId('admin'))->countAllResults());

        $db->table('group_birthday_audiences')->where('group_id', $this->groupId('user'))->delete();
        $this->assertSame(0, BirthdayAudiences::seedDefaults(), 'A configured table is never overwritten.');
        $this->assertSame(6, $db->table('group_birthday_audiences')->countAllResults());
    }

    public function testTheMigrationGoesDownAndUpAgain(): void
    {
        $file = dirname(__DIR__) . '/Database/Migrations/2026-11-15-100001_CreateGroupBirthdayAudiences.php';
        require_once $file;
        $migration = new \Modules\Users\Database\Migrations\CreateGroupBirthdayAudiences(\Config\Database::forge('tests'));
        $db        = db_connect();

        $migration->down();
        $db->resetDataCache();
        $this->assertFalse($db->tableExists('group_birthday_audiences'));

        $migration->up();
        $db->resetDataCache();
        $this->assertTrue($db->tableExists('group_birthday_audiences'));
        $this->assertSame(7, $db->table('group_birthday_audiences')->countAllResults(), 'Defaults for sites whose groups exist.');
    }

    private function profile(User $user, string $date, bool $share, string $name): void
    {
        $db = db_connect();
        $db->table('user_profiles')->where('user_id', $user->id)->delete();
        $db->table('user_profiles')->insert(['user_id' => $user->id, 'birth_date' => $date, 'share_birthday' => $share ? 1 : 0, 'name' => $name]);
    }

    private function groupId(string $name): int
    {
        return (int) db_connect()->table('groups')->where('name', $name)->get()->getRowArray()['id'];
    }

    /**
     * Names in the shared calendar in May 2027, sorted.
     *
     * @return list<string>
     */
    private function names(User $viewer): array
    {
        $names = array_map(static fn (CalendarEvent $e): string => (string) preg_replace('/^Aniversari de /', '', $e->titleIn('ca')), $this->events(new BirthdaysProvider(), $viewer, '2027-05-01', '2027-06-01'));
        sort($names);

        return $names;
    }

    /**
     * @param list<CalendarEvent> $events
     *
     * @return list<string>
     */
    private function titles(array $events): array
    {
        $names = array_map(static fn (CalendarEvent $e): string => (string) preg_replace('/^Aniversari de /', '', $e->titleIn('ca')), $events);
        sort($names);

        return $names;
    }

    /**
     * @return list<CalendarEvent>
     */
    private function events(BirthdaysProvider|AdminBirthdaysProvider $provider, User $user, string $from, string $to): array
    {
        $events = $provider->events(new DateTimeImmutable($from), new DateTimeImmutable($to), $user);

        return is_array($events) ? array_values($events) : iterator_to_array($events, false);
    }
}
