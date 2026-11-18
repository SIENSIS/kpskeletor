<?php

declare(strict_types=1);

namespace Modules\Tasks\Config;

use Modules\Core\Config\ModuleManifest;

/**
 * Manifest of the Tasks tutorial module: a small manager of to-do tasks (projects, to-do tasks,
 * assignees, due dates, labels) holding the finished code of every tutorial (docs/tutorials/,
 * `modules/Tasks/CLAUDE.md` maps each tutorial to its files). Installed **disabled**
 * (`$enabledByDefault`, D-32-01): enable it in Modules (and load `php spark modules:demo Tasks`).
 *
 * Wording (D-00-56): "to-do task" for the records of this module, "scheduled task" for the
 * scheduler's jobs (this module's `Tasks/` folder).
 *
 * Resources (permission names `tasks-<resource>.<operation>`):
 * - `tasks` (kpcrudy, tutorials 1–2): the to-do tasks; extras `complete` (operation and bulk
 *   action) and `estimates` (see and write the estimate, `hideFields()`);
 * - `projects` (kpcrudy): the projects (translated name and description);
 * - `board` (page, tutorial 3): the board; every group;
 * - `requests` (kpcrudy, tutorial 12): requests sent from the public form; extras `accept`
 *   (creates a to-do task) and `reject`.
 * The API (tutorial 11) reuses `tasks-tasks` and `tasks-projects` (same permissions as the CRUDs).
 */
final class Module extends ModuleManifest
{
    public string $name        = 'Tasks';
    public string $version     = '0.2.0';
    public string $icon        = 'bi bi-kanban';
    public string $description = 'Tasks.module.description';
    public string $author      = 'kpskeletor';
    public bool $enabledByDefault = false;
    public array $requires     = ['Core' => '^1.0', 'Auth' => '^0.2', 'Dashboard' => '^0.1', 'Files' => '^0.1'];

    public array $resources = [
        'tasks' => [
            'label'  => 'Tasks.res.tasks',
            'type'   => 'crud',
            'except' => ['import'],
            'extra'  => [
                'complete'  => 'Tasks.perm.complete',
                'estimates' => 'Tasks.perm.estimates',
            ],
        ],
        'projects' => [
            'label'  => 'Tasks.res.projects',
            'type'   => 'crud',
            'except' => ['import', 'trash', 'restore', 'purge'],
        ],
        'board' => [
            'label' => 'Tasks.res.board',
            'type'  => 'page',
        ],
        'requests' => [
            'label'  => 'Tasks.res.requests',
            'type'   => 'crud',
            'except' => ['add', 'edit', 'import', 'trash', 'restore', 'purge'],
            'extra'  => [
                'accept' => 'Tasks.perm.accept',
                'reject' => 'Tasks.perm.reject',
            ],
        ],
    ];

    public array $defaultGrants = [
        'superadmin' => ['tasks-board.*'],
        'admin'      => ['tasks-tasks.*', 'tasks-projects.*', 'tasks-board.*', 'tasks-requests.*'],
        'editor'     => [
            'tasks-tasks.list', 'tasks-tasks.show', 'tasks-tasks.add', 'tasks-tasks.edit', 'tasks-tasks.complete',
            'tasks-projects.list', 'tasks-projects.show',
            'tasks-board.*',
        ],
        'user' => ['tasks-board.*'],
    ];

    public array $menu = [
        ['resource' => 'board', 'label' => 'Tasks.menu.board', 'icon' => 'bi bi-kanban', 'order' => 40],
        ['resource' => 'tasks', 'label' => 'Tasks.menu.tasks', 'icon' => 'bi bi-check2-square', 'order' => 1, 'parent' => 'board'],
        ['resource' => 'projects', 'label' => 'Tasks.menu.projects', 'icon' => 'bi bi-folder2', 'order' => 2, 'parent' => 'board'],
        ['resource' => 'requests', 'label' => 'Tasks.menu.requests', 'icon' => 'bi bi-inbox', 'order' => 3, 'parent' => 'board'],
    ];

    /** Link type of the public menu editor (tutorial 5). */
    public array $menuLinks = [\Modules\Tasks\Menu\ProjectLink::class];

    /** Settings (tutorial 8): Settings › Tasks; read with `setting('Tasks.<key>')`. */
    public array $settings = [
        'reminderGraceDays' => [
            'type'    => 'int',
            'default' => 0,
            'rules'   => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[30]',
            'label'   => 'Tasks.settings.reminderGraceDays',
            'help'    => 'Tasks.settings.reminderGraceDaysHelp',
            'group'   => 'reminders',
        ],
        'publicRequests' => [
            'type'    => 'bool',
            'default' => true,
            'label'   => 'Tasks.settings.publicRequests',
            'help'    => 'Tasks.settings.publicRequestsHelp',
            'group'   => 'public',
        ],
    ];

    /** Dashboard widgets (tutorial 4): one server-side, one asynchronous. */
    public array $widgets = [
        \Modules\Tasks\Widgets\MyPendingTasks::class,
        \Modules\Tasks\Widgets\TaskActivityChart::class,
    ];

    /** Scheduled tasks (tutorial 7). */
    public array $tasks = [\Modules\Tasks\Tasks\OverdueReminder::class];

    /** Notification types (tutorials 6, 7 and 12). */
    public array $notifications = [
        \Modules\Tasks\Notifications\TaskAssigned::class,
        \Modules\Tasks\Notifications\TaskOverdue::class,
        \Modules\Tasks\Notifications\TaskRequested::class,
    ];

    /** Attachments: files of the Storage service in `modules/tasks` (private disk, tutorial 10). */
    public array $backup = ['files' => ['modules/tasks']];

    /** No extra Content-Security-Policy sources. */
    public array $csp = [];

    /** No outbound requests. */
    public array $outboundHosts = [];

    /**
     * Calendars (tutorial 6): `tasks.due` (push: the due dates of open to-do tasks, seen by
     * their assignees and creator) and `tasks.projects` (pull: the projects' deadlines, a
     * `CalendarProvider`, for users who may list the projects).
     */
    public array $calendars = [
        'due'      => ['label' => 'Tasks.cal.due', 'color' => '#fd7e14', 'source' => \Modules\Tasks\Calendar\DueDates::class],
        'projects' => ['label' => 'Tasks.cal.projects', 'color' => '#6610f2', 'permission' => 'tasks-projects.list', 'hidden' => true],
    ];

    public array $calendarProviders = [\Modules\Tasks\Calendar\ProjectDeadlines::class];

    /** API resources (tutorial 11, kpapy). */
    public array $api = [
        'tasks'    => ['path' => 'tasks/tasks', 'handler' => '\Modules\Tasks\Controllers\Api\Tasks::index'],
        'projects' => ['path' => 'tasks/projects', 'handler' => '\Modules\Tasks\Controllers\Api\Projects::index'],
    ];
}
