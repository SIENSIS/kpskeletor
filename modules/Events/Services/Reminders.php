<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Modules\Events\Notifications\EventReminder;

/**
 * The reminders users ask for in the event details (`event_reminders`), and their sending by the
 * SendReminders task: one notification per occurrence, `minutes_before` its start, only while the
 * user can still see the event (the visibility is checked again when sending).
 */
final class Reminders
{
    /**
     * The user's reminder of an event (minutes before), or null.
     */
    public function of(int $eventId, int $userId): ?int
    {
        $row = db_connect()->table('event_reminders')->select('minutes_before')
            ->where('event_id', $eventId)->where('user_id', $userId)->get()->getRowArray();

        return is_array($row) ? (int) $row['minutes_before'] : null;
    }

    /**
     * Sets (or replaces) the user's reminder; `null` removes it.
     */
    public function set(int $eventId, int $userId, ?int $minutes): void
    {
        $table = db_connect()->table('event_reminders');
        $table->where('event_id', $eventId)->where('user_id', $userId)->delete();

        if ($minutes !== null) {
            db_connect()->table('event_reminders')->insert([
                'event_id'       => $eventId,
                'user_id'        => $userId,
                'minutes_before' => $minutes,
                'last_sent_for'  => null,
                'created_at'     => Time::now()->toDateTimeString(),
            ]);
        }
    }

    /**
     * Sends the reminders whose time has come (an occurrence starting within its
     * `minutes_before` from now and not reminded yet).
     *
     * @return int notifications sent
     */
    public function sendDue(?DateTimeInterface $now = null): int
    {
        $zone    = new DateTimeZone(site_timezone());
        $storage = new DateTimeZone(config('App')->appTimezone ?: 'UTC');
        $now     = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable())->setTimezone($zone);
        $repo    = new EventRepository();
        $recur   = new Recurrence(maxOccurrences: (int) config(\Modules\Events\Config\Calendar::class)->maxOccurrences);
        $users   = model(UserModel::class);
        $sent    = 0;

        $rows = db_connect()->table('event_reminders')
            ->select('event_reminders.*')
            ->join('events', 'events.id = event_reminders.event_id')
            ->where('events.deleted_at', null)
            ->where('events.status !=', 'cancelled')
            ->get()->getResultArray();

        foreach ($rows as $reminder) {
            $occurrence = $recur->next($this->event((int) $reminder['event_id']), $now, $now->modify('+' . (int) $reminder['minutes_before'] . ' minutes'));

            if ($occurrence === null) {
                continue;
            }

            $startUtc = $occurrence['start']->setTimezone($storage)->format('Y-m-d H:i:s');

            if ($reminder['last_sent_for'] !== null && (string) $reminder['last_sent_for'] >= $startUtc) {
                continue;
            }

            // Marked as handled even when the user can no longer see the event (nothing is sent).
            db_connect()->table('event_reminders')->where('id', (int) $reminder['id'])->update(['last_sent_for' => $startUtc]);

            $user = $users->findById((int) $reminder['user_id']);

            if (! $user instanceof User) {
                continue;
            }

            $event = $repo->find($user, (int) $reminder['event_id']);

            if ($event === null) {
                continue;
            }

            notify($user, new EventReminder([
                'code'  => EventRepository::code((int) $event['id']),
                'title' => (string) $event['title'],
                'start' => $occurrence['start']->format('Y-m-d H:i'),
                'date'  => $occurrence['start']->format('Y-m-d'),
            ]));
            $sent++;
        }

        return $sent;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(int $id): array
    {
        $row = db_connect()->table('events')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $row : ['starts_at' => '1970-01-01 00:00:00', 'all_day' => 0, 'recur_freq' => null];
    }
}
