<?php

declare(strict_types=1);

namespace Modules\Core\Calendar;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * One event of the calendar API (docs/calendar-api.md): what a module publishes with
 * `service('calendar')->publish()` or returns from a `CalendarProvider`.
 *
 * - `title` and `description`: a text, or texts per language (`['ca' => …, 'es' => …]`; Catalan
 *   is the fallback). The description is plain text or HTML (sanitised when stored and shown).
 * - `start` / `end`: any time zone (stored in UTC); `end` is optional (one hour, or one day for
 *   `allDay`); all-day events use the dates of `start` and `end` (end inclusive).
 * - `calendar`: a calendar declared in the module's manifest (`$calendars`), written
 *   `<module>.<key>` in lower case (e.g. `news.notices`).
 * - `visibility`: `public`, `private` (signed-in users) or `restricted` (only `groups` — group
 *   names — and `users` — user ids).
 * - `url`: link back to the source record ("Edit in …" in the administration and the details).
 * - `reminders`: whether users may ask for a reminder of it.
 * - `key`: the stable key of a provided event (its iCal `UID`); publishers pass the key to
 *   `publish()` instead.
 */
final class CalendarEvent
{
    public const VISIBILITIES = ['public', 'private', 'restricted'];

    public const STATUSES = ['confirmed', 'tentative', 'cancelled'];

    public readonly DateTimeImmutable $start;

    public readonly ?DateTimeImmutable $end;

    /**
     * @param string|array<string, string>      $title
     * @param string|array<string, string>|null $description
     * @param list<string>                      $groups
     * @param list<int>                         $users
     */
    public function __construct(
        public readonly string|array $title,
        DateTimeInterface $start,
        ?DateTimeInterface $end = null,
        public readonly bool $allDay = false,
        public readonly ?string $location = null,
        public readonly string|array|null $description = null,
        public readonly ?string $calendar = null,
        public readonly string $visibility = 'private',
        public readonly array $groups = [],
        public readonly array $users = [],
        public readonly ?string $url = null,
        public readonly bool $reminders = false,
        public readonly string $status = 'confirmed',
        public readonly ?string $color = null,
        public readonly ?string $key = null,
    ) {
        $this->start = DateTimeImmutable::createFromInterface($start);
        $this->end   = $end === null ? null : DateTimeImmutable::createFromInterface($end);

        if ($this->titles() === []) {
            throw new InvalidArgumentException('A calendar event needs a title.');
        }

        if (! in_array($visibility, self::VISIBILITIES, true)) {
            throw new InvalidArgumentException("Unknown visibility \"{$visibility}\".");
        }

        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException("Unknown status \"{$status}\".");
        }

        if ($this->end !== null && $this->end < $this->start) {
            throw new InvalidArgumentException('A calendar event cannot end before it starts.');
        }

        if ($calendar !== null && preg_match('/^[a-z][a-z0-9]*\.[a-z][a-z0-9]*$/', $calendar) !== 1) {
            throw new InvalidArgumentException("Invalid calendar \"{$calendar}\" (<module>.<key>).");
        }

        if ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            throw new InvalidArgumentException("Invalid colour \"{$color}\".");
        }

        if ($url !== null && preg_match('#^(https?://|/)#', $url) !== 1) {
            throw new InvalidArgumentException('The URL of a calendar event must be http(s) or an internal path.');
        }

        if ($key !== null && preg_match('/^[A-Za-z0-9._:-]{1,150}$/', $key) !== 1) {
            throw new InvalidArgumentException("Invalid key \"{$key}\".");
        }
    }

    /**
     * The titles per language (non-empty ones; a plain text is Catalan).
     *
     * @return array<string, string>
     */
    public function titles(): array
    {
        return self::texts($this->title);
    }

    /**
     * @return array<string, string>
     */
    public function descriptions(): array
    {
        return self::texts($this->description ?? []);
    }

    /**
     * The title in a language, with the Catalan fallback.
     */
    public function titleIn(string $locale): string
    {
        $titles = $this->titles();

        return $titles[$locale] ?? $titles['ca'] ?? (string) reset($titles);
    }

    public function descriptionIn(string $locale): string
    {
        $texts = $this->descriptions();

        return $texts[$locale] ?? $texts['ca'] ?? (string) (reset($texts) ?: '');
    }

    /**
     * @param string|array<array-key, mixed> $value
     *
     * @return array<string, string>
     */
    private static function texts(string|array $value): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : ['ca' => $value];
        }

        $texts = [];

        foreach ($value as $locale => $text) {
            if (is_string($locale) && is_string($text) && trim($text) !== '') {
                $texts[$locale] = $text;
            }
        }

        return $texts;
    }
}
