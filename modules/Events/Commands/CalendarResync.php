<?php

declare(strict_types=1);

namespace Modules\Events\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Events\Services\CalendarService;
use Throwable;

/**
 * php spark calendar:resync <Module>
 *
 * Asks the module's calendar sources (`CalendarSource::all()`, declared as `'source'` in its
 * `$calendars`) for every event again, publishes them and unpublishes the published events they no
 * longer return (docs/calendar-api.md, "Consistency").
 */
final class CalendarResync extends BaseCommand
{
    protected $group       = 'Calendar';
    protected $name        = 'calendar:resync';
    protected $description = 'Publishes again every calendar event of a module (from its calendar sources) and removes stale ones.';
    protected $usage       = 'calendar:resync <Module>';

    /**
     * @var array<string, string>
     */
    protected $arguments = ['Module' => 'The module (e.g. News).'];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $module = (string) ($params[0] ?? CLI::prompt(lang('Core.make.nameMissing'), null, 'required'));

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $module) !== 1) {
            CLI::error(lang('Events.resync.invalid', [$module]));

            return EXIT_ERROR;
        }

        try {
            $result = (new CalendarService())->resync($module);
        } catch (Throwable $e) {
            CLI::error(lang('Events.resync.failed', [$module, $e->getMessage()]));

            return EXIT_ERROR;
        }

        if ($result['calendars'] === 0) {
            CLI::write(lang('Events.resync.noSource', [$module]), 'yellow');

            return EXIT_SUCCESS;
        }

        activity_log('Events', 'calendar_resync', ['resource' => 'events-events', 'summary' => $module, 'changes' => $result]);
        CLI::write(lang('Events.resync.done', [$module, $result['published'], $result['unchanged'], $result['unpublished']]), 'green');

        return EXIT_SUCCESS;
    }
}
