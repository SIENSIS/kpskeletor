<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use CodeIgniter\Shield\Entities\User;
use DateTimeImmutable;
use Modules\Core\Scheduler\Schedule;
use Modules\Users\Notifications\BirthdayGreeting;
use Modules\Users\Services\BirthdayGreetings;
use Modules\Users\Tasks\SendBirthdayGreetings;

/**
 * Birthday greetings (D-25-09): only on the day, once a year, only the chosen groups, only users
 * who share their birthday unless the setting says otherwise, the message per language with
 * `{name}`, the time from a setting, and the switch in the Groups form.
 *
 * @internal
 */
final class BirthdayGreetingsTest extends UsersTestCase
{
    private User $teacher;

    private User $private;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = $this->createUser('profe@example.com', 'editor');
        $this->private = $this->createUser('reservat@example.com', 'editor');
        $this->student = $this->createUser('alumne@example.com', 'user');

        $this->profile($this->teacher, '1980-05-17', true, 'Profe A', 'ca');
        $this->profile($this->private, '1982-05-17', false, 'Profe B', 'es');
        $this->profile($this->student, '2010-05-17', true, 'Alumne', 'ca');

        db_connect()->table('notifications')->emptyTable();
        BirthdayGreetings::setGreets($this->groupId('editor'), true); // greet the teachers only
    }

    public function testOnlyTheChosenGroupsWhoShareTheirBirthdayAreGreetedOnTheDay(): void
    {
        $this->assertSame(0, (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-16')), 'Not the day before.');

        $this->assertSame(1, (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-17')));
        $this->assertSame([(int) $this->teacher->id], $this->greeted(), 'Profe B does not share; the students are not greeted.');
        $this->assertSame(0, (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-17')), 'Once a year.');
    }

    public function testTheSettingCanGreetEveryMemberOfTheChosenGroups(): void
    {
        setting('Users.birthdayGreetingSharedOnly', false);

        $this->assertSame(2, (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-17')));
        $this->assertSame([(int) $this->teacher->id, (int) $this->private->id], $this->greeted());
    }

    public function testNoGroupChosenGreetsNobody(): void
    {
        BirthdayGreetings::setGreets($this->groupId('editor'), false);

        $this->assertSame(0, (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-17')));
    }

    public function testTheMessageIsTheAdministratorsInTheUsersLanguage(): void
    {
        setting('Users.birthdayGreeting', ['ca' => 'Molts anys, {name}! Bon dia de part de la direcció.', 'es' => '¡Felicidades, {name}!']);
        setting('Users.birthdayGreetingSharedOnly', false);
        (new BirthdayGreetings())->sendToday(new DateTimeImmutable('2027-05-17'));

        $this->assertSame('Molts anys, Profe A! Bon dia de part de la direcció.', $this->render((int) $this->teacher->id, 'ca'));
        $this->assertSame('¡Felicidades, Profe B!', $this->render((int) $this->private->id, 'es'));
        $this->assertSame('Molts anys, Profe A! Bon dia de part de la direcció.', BirthdayGreetings::message('en', 'Profe A'), 'A language without a text uses the Catalan one.');
    }

    public function testWithoutAStoredGreetingEachLanguageGetsItsDefault(): void
    {
        $this->assertSame('Per molts anys, Profe A! 🎉', BirthdayGreetings::message('ca', 'Profe A'));
        $this->assertSame('¡Feliz cumpleaños, Profe A! 🎉', BirthdayGreetings::message('es', 'Profe A'));
        $this->assertSame('Happy birthday, Profe A! 🎉', BirthdayGreetings::message('en', 'Profe A'));
        $this->assertSame(
            ['ca' => 'Per molts anys, {name}! 🎉', 'en' => 'Happy birthday, {name}! 🎉', 'es' => '¡Feliz cumpleaños, {name}! 🎉'],
            setting('Users.birthdayGreeting'),
            'The default is the language file\'s text in every locale.',
        );
    }

    public function testTheTwentyNinthOfFebruaryIsGreetedOnTheTwentyEighthInCommonYears(): void
    {
        $this->assertTrue(BirthdayGreetings::isBirthday('2000-02-29', new DateTimeImmutable('2027-02-28')));
        $this->assertFalse(BirthdayGreetings::isBirthday('2000-02-29', new DateTimeImmutable('2028-02-28')));
        $this->assertTrue(BirthdayGreetings::isBirthday('2000-02-29', new DateTimeImmutable('2028-02-29')));
        $this->assertFalse(BirthdayGreetings::isBirthday('2000-03-01', new DateTimeImmutable('2027-02-28')));
    }

    public function testTheTimeOfTheTaskComesFromTheSetting(): void
    {
        $this->assertSame(Schedule::daily('08:00')->expression(), (new SendBirthdayGreetings())->schedule()->expression());

        setting('Users.birthdayGreetingTime', '07:30');
        $this->assertSame(Schedule::daily('07:30')->expression(), (new SendBirthdayGreetings())->schedule()->expression());

        setting('Users.birthdayGreetingTime', '25:99');
        $this->assertSame(Schedule::daily('08:00')->expression(), (new SendBirthdayGreetings())->schedule()->expression(), 'An invalid value falls back to 08:00.');
    }

    public function testTheGroupsFormHasTheSwitch(): void
    {
        $this->createUser('direccio@example.com', 'admin');
        $this->login('direccio@example.com');
        $user  = $this->groupId('user');
        $code  = $this->code($user, 'groups');
        $form  = ['title' => 'User', 'description' => '', 'two_factor' => 'optional', 'birthday_groups' => [(string) $user]];

        $this->assertStringContainsString('name="birthday_greet"', (string) $this->send('GET', 'backend/users/groups/edit/' . $code)->response()->getBody());

        $this->send('POST', 'backend/users/groups/edit/' . $code, $form + ['birthday_greet' => '1']);
        $this->assertTrue(BirthdayGreetings::greets($user));

        $this->send('POST', 'backend/users/groups/edit/' . $code, $form + ['birthday_greet' => '0']);
        $this->assertFalse(BirthdayGreetings::greets($user));
    }

    public function testTheMigrationsGoDownAndUpAgain(): void
    {
        $db = db_connect();

        foreach (['2026-11-15-100003_CreateBirthdayGreetingsSent' => 'birthday_greetings_sent', '2026-11-15-100002_CreateGroupBirthdayGreetings' => 'group_birthday_greetings'] as $file => $table) {
            require_once dirname(__DIR__) . '/Database/Migrations/' . $file . '.php';
            $class     = 'Modules\Users\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', $file);
            $migration = new $class(\Config\Database::forge('tests'));

            $migration->down();
            $db->resetDataCache();
            $this->assertFalse($db->tableExists($table));
            $migration->up();
            $db->resetDataCache();
            $this->assertTrue($db->tableExists($table));
        }
    }

    public function testTheStoredGreetingsAreMergedIntoOneTranslatedSetting(): void
    {
        require_once dirname(__DIR__) . '/Database/Migrations/2026-11-17-100001_MergeBirthdayGreetingSettings.php';
        $migration = new \Modules\Users\Database\Migrations\MergeBirthdayGreetingSettings(\Config\Database::forge('tests'));
        $db        = db_connect();
        $now       = date('Y-m-d H:i:s');

        foreach (['birthdayGreetingCa' => 'Molts anys, {name}!', 'birthdayGreetingEn' => 'Cheers, {name}!', 'birthdayGreetingEs' => ''] as $key => $value) {
            $db->table('settings')->insert(['class' => 'Users', 'key' => $key, 'value' => $value, 'type' => 'string', 'context' => null, 'created_at' => $now, 'updated_at' => $now]);
        }

        $migration->up();
        $row = $db->table('settings')->where('class', 'Users')->where('key', 'birthdayGreeting')->get()->getRowArray();
        $this->assertSame(['ca' => 'Molts anys, {name}!', 'en' => 'Cheers, {name}!'], unserialize((string) $row['value']));
        $this->assertSame(0, $db->table('settings')->like('key', 'birthdayGreeting', 'after')->where('key !=', 'birthdayGreeting')->where('key !=', 'birthdayGreetingSharedOnly')->where('key !=', 'birthdayGreetingTime')->countAllResults());

        \Config\Services::resetSingle('settings'); // forget the values read before the migration
        $this->assertSame('Cheers, Profe A!', BirthdayGreetings::message('en', 'Profe A'));
        $this->assertSame('Molts anys, Profe A!', BirthdayGreetings::message('es', 'Profe A'), 'Spanish had no text: the Catalan one.');

        $migration->down();
        $this->assertSame(['birthdayGreetingCa', 'birthdayGreetingEn'], array_column($db->table('settings')->whereIn('key', ['birthdayGreetingCa', 'birthdayGreetingEs', 'birthdayGreetingEn', 'birthdayGreeting'])->orderBy('key')->get()->getResultArray(), 'key'));

        $migration->up();
        $this->assertSame(1, $db->table('settings')->where('key', 'birthdayGreeting')->countAllResults());
    }

    private function profile(User $user, string $date, bool $share, string $name, string $locale): void
    {
        db_connect()->table('user_profiles')->insert(['user_id' => $user->id, 'birth_date' => $date, 'share_birthday' => $share ? 1 : 0, 'name' => $name, 'locale' => $locale]);
    }

    private function groupId(string $name): int
    {
        return (int) db_connect()->table('groups')->where('name', $name)->get()->getRowArray()['id'];
    }

    /**
     * @return list<int>
     */
    private function greeted(): array
    {
        $ids = array_map('intval', array_column(db_connect()->table('notifications')->where('type', BirthdayGreeting::class)->get()->getResultArray(), 'user_id'));
        sort($ids);

        return $ids;
    }

    private function render(int $userId, string $locale): string
    {
        $row = db_connect()->table('notifications')->where('type', BirthdayGreeting::class)->where('user_id', $userId)->get()->getRowArray();
        $this->assertIsArray($row);
        $data = json_decode((string) $row['data'], true);
        service('language')->setLocale($locale);

        return BirthdayGreeting::fromData(is_array($data) ? $data : [])->render()->body;
    }
}
