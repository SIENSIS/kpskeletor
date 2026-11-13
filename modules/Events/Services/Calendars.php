<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Calendar\EventAccessPolicy;
use Modules\Core\Permissions\Authorization;
use Modules\Core\Registry\DeclarationRegistry;

/**
 * The calendars of the calendar view and the feeds (docs/calendar-api.md, "Calendars"):
 *
 * - `events`: the events created by hand in the administration (`events.calendar` is null);
 * - every calendar declared in the manifest `$calendars` of an **enabled** module, with the id
 *   `<module in lower case>.<key>` (e.g. `news.notices`). A calendar with a `permission` is only
 *   shown to users holding it; without one, each event's visibility decides (D-00-41).
 *
 * Each user may hide calendars in the calendar view (`event_calendar_preferences`).
 */
final class Calendars
{
    /** Id of the calendar of the events created by hand. */
    public const OWN = 'events';

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * Every calendar of enabled modules, the own one first: id => definition.
     *
     * @return array<string, array{id: string, module: string, label: string, color: string|null, permission: string|null, policy: string|null, source: string|null}>
     */
    public function all(): array
    {
        $calendars = [self::OWN => [
            'id' => self::OWN, 'module' => 'Events', 'label' => lang('Events.calendars.own'), 'color' => null,
            'permission' => null, 'policy' => null, 'source' => null,
        ]];

        foreach ($this->registry->calendars() as $key => $declaration) {
            [$module, $name] = explode('.', $key, 2) + [1 => ''];
            $definition      = $declaration['definition'];
            $id              = self::id($module, $name);
            $calendars[$id]  = [
                'id'         => $id,
                'module'     => $module,
                'label'      => lang((string) ($definition['label'] ?? $id)),
                'color'      => EventRepository::color($definition['color'] ?? null),
                'permission' => is_string($definition['permission'] ?? null) && $definition['permission'] !== '' ? $definition['permission'] : null,
                'policy'     => is_string($definition['policy'] ?? null) ? $definition['policy'] : null,
                'source'     => is_string($definition['source'] ?? null) ? $definition['source'] : null,
            ];
        }

        return $calendars;
    }

    /**
     * The calendars a user (or a guest: `null`) may see events of.
     *
     * @return array<string, array{id: string, module: string, label: string, color: string|null, permission: string|null, policy: string|null, source: string|null}>
     */
    public function visibleTo(?User $user): array
    {
        $authorization = Authorization::for($user);

        return array_filter($this->all(), static fn (array $calendar): bool => $calendar['permission'] === null
            || ($user !== null && $authorization->can($calendar['permission'])));
    }

    /**
     * The calendar id of a module's calendar key ("News", "notices" → "news.notices").
     */
    public static function id(string $module, string $key): string
    {
        return strtolower($module) . '.' . $key;
    }

    /**
     * The id of an event's calendar (`events.calendar`, null for events created by hand).
     *
     * @param array<string, mixed> $row
     */
    public static function of(array $row): string
    {
        $calendar = $row['calendar'] ?? null;

        return is_string($calendar) && $calendar !== '' ? $calendar : self::OWN;
    }

    /**
     * The access policy of a calendar, or null.
     */
    public function policy(string $calendar): ?EventAccessPolicy
    {
        $class = $this->all()[$calendar]['policy'] ?? null;

        return is_string($class) && is_a($class, EventAccessPolicy::class, true) ? new $class() : null;
    }

    /**
     * The calendars the user has hidden in the calendar view.
     *
     * @return list<string>
     */
    public function hidden(User $user): array
    {
        $row = db_connect()->table('event_calendar_preferences')->where('user_id', (int) $user->id)->get()->getRowArray();

        return is_array($row) ? self::split($row['hidden'] ?? null) : [];
    }

    /**
     * Stores the hidden calendars (only ids of calendars the user can see).
     *
     * @param list<string> $hidden
     *
     * @return list<string> what was stored
     */
    public function setHidden(User $user, array $hidden): array
    {
        $hidden = array_values(array_intersect(array_keys($this->visibleTo($user)), $hidden));
        $db     = db_connect();
        $values = ['hidden' => $hidden === [] ? null : implode(',', $hidden), 'updated_at' => Time::now()->toDateTimeString()];

        if ($db->table('event_calendar_preferences')->where('user_id', (int) $user->id)->countAllResults() > 0) {
            $db->table('event_calendar_preferences')->where('user_id', (int) $user->id)->update($values);
        } else {
            $db->table('event_calendar_preferences')->insert($values + ['user_id' => (int) $user->id]);
        }

        return $hidden;
    }

    /**
     * A comma-separated list of ids as a list.
     *
     * @return list<string>
     */
    public static function split(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $value)), static fn (string $id): bool => preg_match('/^[a-z][a-z0-9]*(\.[a-z][a-z0-9]*)?$/', $id) === 1)));
    }
}
