<?php

declare(strict_types=1);

namespace Modules\Users\Services;

use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use DateTimeZone;
use Modules\Core\Settings\TranslatedText;
use Modules\Users\Calendar\Birthdays;
use Modules\Users\Notifications\BirthdayGreeting;

/**
 * Birthday greetings (D-25-09): on their birthday (in the site's time zone; 29 February → 28
 * February in common years), active, non-banned members of the groups chosen in Users › Groups
 * ("Greet the members on their birthday") receive the `BirthdayGreeting` notification, once a
 * year. With `Users.birthdayGreetingSharedOnly` (on by default) only users who share their
 * birthday (`user_profiles.share_birthday`) are greeted. The greeting only reaches the person.
 */
final class BirthdayGreetings
{
    /**
     * Sends today's greetings. Returns how many users were greeted.
     */
    public function sendToday(?DateTimeImmutable $today = null): int
    {
        $zone   = new DateTimeZone(function_exists('site_timezone') ? site_timezone() : 'UTC');
        $today ??= new DateTimeImmutable(Time::now($zone)->toDateString(), $zone);
        $groups = self::greetedGroups();

        if ($groups === []) {
            return 0;
        }

        $builder = Birthdays::people()
            ->whereIn('user_profiles.user_id', static fn ($sub) => $sub->select('user_id')->from('auth_groups_users')->whereIn('group', $groups));

        if ((bool) (setting('Users.birthdayGreetingSharedOnly') ?? true)) {
            $builder->where('user_profiles.share_birthday', 1);
        }

        $year = (int) $today->format('Y');
        $db   = db_connect();
        $sent = 0;

        foreach ($builder->get()->getResultArray() as $row) {
            if (! self::isBirthday((string) $row['birth_date'], $today)) {
                continue;
            }

            // Recorded first: a failure while sending never greets twice.
            if ($db->table('birthday_greetings_sent')->where('user_id', (int) $row['user_id'])->where('year', $year)->countAllResults() > 0) {
                continue;
            }

            $db->table('birthday_greetings_sent')->insert(['user_id' => (int) $row['user_id'], 'year' => $year, 'sent_at' => Time::now()->toDateTimeString()]);
            $name = trim((string) ($row['name'] ?? '')) !== '' ? trim((string) $row['name']) : (string) $row['username'];
            notify((int) $row['user_id'], new BirthdayGreeting(['name' => $name]));
            $sent++;
        }

        $db->table('birthday_greetings_sent')->where('year <', $year - 1)->delete();

        return $sent;
    }

    /**
     * Whether a date of birth (`YYYY-MM-DD`) is a birthday on this day.
     */
    public static function isBirthday(string $birthDate, DateTimeImmutable $day): bool
    {
        if (preg_match('/^\d{4}-(\d{2})-(\d{2})/', $birthDate, $m) !== 1) {
            return false;
        }

        $monthDay = $m[1] . '-' . $m[2];

        if ($monthDay === '02-29' && ! checkdate(2, 29, (int) $day->format('Y'))) {
            $monthDay = '02-28';
        }

        return $monthDay === $day->format('m-d');
    }

    /**
     * The greeting for a language, with `{name}` replaced (plain text: escaped where shown).
     */
    public static function message(string $locale, string $name): string
    {
        // The administrator's text of that language, else the Catalan one, else the default.
        $message = TranslatedText::get('Users.birthdayGreeting', $locale);

        if ($message === '') {
            $message = lang('Users.greetings.defaultMessage', [], $locale);
        }

        return str_replace('{name}', $name, $message);
    }

    /**
     * Names of the groups whose members are greeted.
     *
     * @return list<string>
     */
    public static function greetedGroups(): array
    {
        $rows = db_connect()->table('group_birthday_greetings')->select('groups.name')
            ->join('groups', 'groups.id = group_birthday_greetings.group_id')
            ->get()->getResultArray();

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    /**
     * Whether a group's members are greeted.
     */
    public static function greets(int $groupId): bool
    {
        return db_connect()->table('group_birthday_greetings')->where('group_id', $groupId)->countAllResults() > 0;
    }

    /**
     * Turns the greeting of a group's members on or off.
     */
    public static function setGreets(int $groupId, bool $greet): void
    {
        $db = db_connect();
        $db->table('group_birthday_greetings')->where('group_id', $groupId)->delete();

        if ($greet) {
            $db->table('group_birthday_greetings')->insert(['group_id' => $groupId]);
        }
    }

    /**
     * The task's time (`Users.birthdayGreetingTime`, `HH:MM`), 08:00 when unset or invalid.
     */
    public static function time(): string
    {
        $time = setting('Users.birthdayGreetingTime');

        return is_string($time) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1 ? $time : '08:00';
    }
}
