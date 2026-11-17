<?php

declare(strict_types=1);

namespace Modules\Tasks\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Modules\Core\Content\ContentTranslations;
use Modules\Core\Permissions\Authorization;
use Modules\Tasks\Calendar\DueDates;

/**
 * Reads and the few writes of the to-do tasks shared by the board, the widgets, the public page,
 * the reminder and the API; the rules live here once:
 *
 * - **who sees a to-do task** (board, widget): users with `tasks-tasks.list` see every one, any
 *   other user only those assigned to them;
 * - **who moves a to-do task** on the board: `tasks-tasks.edit`, or one of its assignees;
 * - **public** to-do tasks: `visibility = public`, not in the trash, in an active project (or none).
 */
final class TaskRepository
{
    public const STATUSES     = ['todo', 'doing', 'done'];
    public const PRIORITIES   = ['low', 'normal', 'high'];
    public const VISIBILITIES = ['private', 'public'];
    public const LABELS       = ['bug', 'feature', 'docs', 'design', 'support'];

    /** Done to-do tasks stay on the board for these days. */
    public const BOARD_DONE_DAYS = 14;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /**
     * Project names in the request language (Catalan fallback, then the slug).
     *
     * @return array<int, string> id => name
     */
    public function projectNames(bool $activeOnly = false): array
    {
        $builder = $this->db()->table('task_projects')->select('id, slug')->orderBy('slug');

        if ($activeOnly) {
            $builder->where('active', 1);
        }

        $rows  = $builder->get()->getResultArray();
        $names = $this->projectTexts(array_map(static fn (array $row): int => (int) $row['id'], $rows));
        $list  = [];

        foreach ($rows as $row) {
            $list[(int) $row['id']] = $names[(int) $row['id']]['name'] ?? (string) $row['slug'];
        }

        asort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }

    /**
     * Name and description of projects in the request language (Catalan fallback).
     *
     * @param list<int> $ids
     *
     * @return array<int, array{name: string, description: string}>
     */
    public function projectTexts(array $ids): array
    {
        $texts = [];

        foreach (ContentTranslations::texts('task_project_translations', 'project_id', $ids, ['name', 'description']) as $id => $fields) {
            $texts[$id] = ['name' => $fields['name'], 'description' => $fields['description']];
        }

        return $texts;
    }

    /**
     * An active project by slug, with its texts, or null.
     *
     * @return array{id: int, slug: string, color: string, ends_on: string|null, name: string, description: string}|null
     */
    public function activeProject(string $slug): ?array
    {
        $row = $this->db()->table('task_projects')->where('slug', $slug)->where('active', 1)->get()->getRowArray();

        if (! is_array($row)) {
            return null;
        }

        $texts = $this->projectTexts([(int) $row['id']])[(int) $row['id']] ?? ['name' => (string) $row['slug'], 'description' => ''];

        return [
            'id'          => (int) $row['id'],
            'slug'        => (string) $row['slug'],
            'color'       => (string) $row['color'],
            'ends_on'     => is_string($row['ends_on'] ?? null) ? $row['ends_on'] : null,
            'name'        => $texts['name'] !== '' ? $texts['name'] : (string) $row['slug'],
            'description' => $texts['description'],
        ];
    }

    /**
     * Whether the user sees every to-do task (`tasks-tasks.list`) or only theirs.
     */
    public static function seesAll(User $user): bool
    {
        return Authorization::for($user)->can('tasks-tasks.list');
    }

    /**
     * The to-do tasks of the board: open ones and those done in the last BOARD_DONE_DAYS days;
     * every one with `tasks-tasks.list`, otherwise those assigned to the user.
     *
     * @return list<array<string, mixed>>
     */
    public function board(User $user, ?int $projectId = null, ?Time $now = null): array
    {
        $now ??= Time::now();
        $builder = $this->visibleTo($user)
            ->groupStart()
            ->where('tasks.status !=', 'done')
            ->orWhere('tasks.completed_at >=', $now->subDays(self::BOARD_DONE_DAYS)->toDateTimeString())
            ->groupEnd();

        if ($projectId !== null) {
            $builder->where('tasks.project_id', $projectId);
        }

        $rows = $builder->orderBy('due_on IS NULL', '', false)->orderBy('tasks.due_on')->orderBy('tasks.id')->get()->getResultArray();

        return $this->withAssignees($rows);
    }

    /**
     * Open to-do tasks assigned to a user, the soonest due first.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingFor(int $userId, int $limit): array
    {
        return $this->db()->table('tasks')
            ->select('tasks.id, tasks.title, tasks.status, tasks.priority, tasks.due_on, tasks.project_id')
            ->join('task_assignees', 'task_assignees.task_id = tasks.id')
            ->where('task_assignees.user_id', $userId)
            ->where('tasks.status !=', 'done')
            ->where('tasks.deleted_at', null)
            ->orderBy('due_on IS NULL', '', false)
            ->orderBy('tasks.due_on')
            ->orderBy('tasks.id')
            ->limit(max(1, $limit))
            ->get()->getResultArray();
    }

    /**
     * Open to-do tasks assigned to a user, counted.
     */
    public function pendingCount(int $userId): int
    {
        return $this->db()->table('tasks')
            ->join('task_assignees', 'task_assignees.task_id = tasks.id')
            ->where('task_assignees.user_id', $userId)
            ->where('tasks.status !=', 'done')
            ->where('tasks.deleted_at', null)
            ->countAllResults();
    }

    /**
     * To-do tasks created and completed per day over the last `$days` days (oldest first).
     *
     * @return list<array{date: string, created: int, completed: int}>
     */
    public function activity(int $days, ?Time $now = null): array
    {
        $now ??= Time::now();
        $from = $now->subDays($days - 1)->setTime(0, 0);
        $list = [];

        for ($i = 0; $i < $days; $i++) {
            $list[$from->addDays($i)->toDateString()] = ['date' => $from->addDays($i)->toDateString(), 'created' => 0, 'completed' => 0];
        }

        foreach (['created' => 'created_at', 'completed' => 'completed_at'] as $key => $column) {
            $rows = $this->db()->table('tasks')->select($column)
                ->where($column . ' >=', $from->toDateTimeString())
                ->where('deleted_at', null)
                ->get()->getResultArray();

            foreach ($rows as $row) {
                $date = substr((string) $row[$column], 0, 10);

                if (isset($list[$date])) {
                    $list[$date][$key]++;
                }
            }
        }

        return array_values($list);
    }

    /**
     * Public to-do tasks, optionally of one project.
     *
     * @return list<array<string, mixed>>
     */
    public function publicTasks(?int $projectId = null): array
    {
        $builder = $this->db()->table('tasks')
            ->select('tasks.id, tasks.title, tasks.description, tasks.status, tasks.priority, tasks.labels, tasks.due_on, tasks.project_id')
            ->join('task_projects', 'task_projects.id = tasks.project_id', 'left')
            ->where('tasks.visibility', 'public')
            ->where('tasks.deleted_at', null)
            ->groupStart()->where('tasks.project_id', null)->orWhere('task_projects.active', 1)->groupEnd();

        if ($projectId !== null) {
            $builder->where('tasks.project_id', $projectId);
        }

        return $builder->orderBy('due_on IS NULL', '', false)->orderBy('tasks.due_on')->orderBy('tasks.id')->get()->getResultArray();
    }

    /**
     * Active projects with at least one public to-do task.
     *
     * @return list<array{slug: string, name: string, color: string}>
     */
    public function publicProjects(): array
    {
        $rows = $this->db()->table('task_projects')->select('task_projects.id, task_projects.slug, task_projects.color')
            ->where('task_projects.active', 1)
            ->whereIn('task_projects.id', static fn (BaseBuilder $sub): BaseBuilder => $sub->select('project_id')->from('tasks')->where('visibility', 'public')->where('deleted_at', null)->where('project_id IS NOT NULL', null, false))
            ->get()->getResultArray();
        $names = $this->projectNames(true);
        $list  = [];

        foreach ($rows as $row) {
            $list[] = ['slug' => (string) $row['slug'], 'name' => $names[(int) $row['id']] ?? (string) $row['slug'], 'color' => (string) $row['color']];
        }

        usort($list, static fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * A to-do task (not in the trash) or null.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->db()->table('tasks')->where('id', $id)->where('deleted_at', null)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<int>
     */
    public function assigneeIds(int $taskId): array
    {
        $rows = $this->db()->table('task_assignees')->select('user_id')->where('task_id', $taskId)->orderBy('user_id')->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    public function isAssignee(int $taskId, int $userId): bool
    {
        return $this->db()->table('task_assignees')->where('task_id', $taskId)->where('user_id', $userId)->countAllResults() > 0;
    }

    /**
     * Whether the user may see a to-do task in the back office (board, attachments).
     *
     * @param array<string, mixed> $task
     */
    public function canSee(User $user, array $task): bool
    {
        return Authorization::for($user)->can('tasks-tasks.show')
            || (int) ($task['created_by'] ?? 0) === (int) $user->id
            || $this->isAssignee((int) $task['id'], (int) $user->id);
    }

    /**
     * Whether the user may move a to-do task on the board: `tasks-tasks.edit` or an assignee.
     *
     * @param array<string, mixed> $task
     */
    public function canMove(User $user, array $task): bool
    {
        return Authorization::for($user)->can('tasks-tasks.edit') || $this->isAssignee((int) $task['id'], (int) $user->id);
    }

    /**
     * Changes the status of a to-do task (`completed_at` follows `done`) and its calendar event.
     */
    public function setStatus(int $id, string $status, ?Time $now = null): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown status ' . $status);
        }

        $now ??= Time::now();
        $this->db()->table('tasks')->where('id', $id)->update([
            'status'       => $status,
            'completed_at' => $status === 'done' ? $now->toDateTimeString() : null,
            'updated_at'   => $now->toDateTimeString(),
        ]);

        DueDates::sync($id);
    }

    /**
     * Open to-do tasks due before today minus the grace days, not reminded today, with their
     * assignees (the overdue reminder, tutorial 7).
     *
     * @return list<array<string, mixed>>
     */
    public function overdue(int $graceDays, ?Time $now = null): array
    {
        $now ??= Time::now();
        $today = $now->toDateString();
        $limit = $now->subDays(max(0, $graceDays))->toDateString();

        $rows = $this->db()->table('tasks')
            ->where('status !=', 'done')
            ->where('deleted_at', null)
            ->where('due_on IS NOT NULL', null, false)
            ->where('due_on <', $limit)
            ->groupStart()->where('reminded_on', null)->orWhere('reminded_on <', $today)->groupEnd()
            ->orderBy('due_on')
            ->get()->getResultArray();

        return $this->withAssignees($rows);
    }

    public function markReminded(int $id, ?Time $now = null): void
    {
        $this->db()->table('tasks')->where('id', $id)->update(['reminded_on' => ($now ?? Time::now())->toDateString()]);
    }

    /**
     * The labels of a stored `labels` value (JSON, as kpcrudy's multiselect writes it).
     *
     * @return list<string>
     */
    public static function labels(mixed $value): array
    {
        $list = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($list) ? array_values(array_filter($list, static fn ($label): bool => is_string($label) && in_array($label, self::LABELS, true))) : [];
    }

    /**
     * The to-do tasks the user may see on the board (not in the trash).
     *
     * @return BaseBuilder<BaseConnection<object|resource, object|resource>>
     */
    private function visibleTo(User $user): BaseBuilder
    {
        $builder = $this->db()->table('tasks')
            ->select('tasks.id, tasks.title, tasks.status, tasks.priority, tasks.labels, tasks.due_on, tasks.project_id, tasks.created_by, tasks.completed_at')
            ->where('tasks.deleted_at', null);

        if (! self::seesAll($user)) {
            $builder->whereIn('tasks.id', static fn (BaseBuilder $sub): BaseBuilder => $sub->select('task_id')->from('task_assignees')->where('user_id', (int) $user->id));
        }

        return $builder;
    }

    /**
     * Adds `assignees` (user ids) to each row.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function withAssignees(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids   = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $links = $this->db()->table('task_assignees')->select('task_id, user_id')->whereIn('task_id', $ids)->orderBy('user_id')->get()->getResultArray();
        $by    = [];

        foreach ($links as $link) {
            $by[(int) $link['task_id']][] = (int) $link['user_id'];
        }

        foreach ($rows as $i => $row) {
            $rows[$i]['assignees'] = $by[(int) $row['id']] ?? [];
        }

        return $rows;
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? db_connect();
    }
}
