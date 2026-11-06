<?php

declare(strict_types=1);

namespace Modules\Core\Scheduler;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use DateTimeImmutable;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The `scheduled_tasks` table kept in line with the manifests: one row per declared task class,
 * `active` = declared by an enabled module and loadable, the cron expression and the next run.
 */
final class ScheduledTasks
{
    /** Date format of the table. */
    public const FORMAT = 'Y-m-d H:i:s';

    /** @var array<string, TaskInterface|null> */
    private array $instances = [];

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db
     */
    public function __construct(
        private readonly ?BaseConnection $db = null,
        private readonly DeclarationRegistry $registry = new DeclarationRegistry(),
    ) {
    }

    /**
     * Inserts new tasks, updates changed schedules (their next run is recalculated) and marks
     * tasks of disabled modules, and tasks no longer declared, as inactive.
     */
    public function sync(DateTimeImmutable $now): void
    {
        $declared = $this->registry->all('task');
        $stored   = [];

        foreach ($this->db()->table('scheduled_tasks')->get()->getResultArray() as $row) {
            $stored[(string) $row['class']] = $row;
        }

        $stamp = $now->format(self::FORMAT);

        foreach ($declared as $class => $declaration) {
            $task = $this->instance($class);
            $row  = $stored[$class] ?? null;

            if ($task === null) {
                if ($row !== null && (int) $row['active'] === 1) {
                    $this->db()->table('scheduled_tasks')->where('id', $row['id'])->update(['active' => 0, 'updated_at' => $stamp]);
                }

                continue;
            }

            $expression = $task->schedule()->expression();

            if ($row === null) {
                $this->db()->table('scheduled_tasks')->insert([
                    'class'       => $class,
                    'module'      => $declaration['module'],
                    'expression'  => $expression,
                    'active'      => 1,
                    'next_run_at' => $task->schedule()->nextRun($now)->format(self::FORMAT),
                    'created_at'  => $stamp,
                    'updated_at'  => $stamp,
                ]);

                continue;
            }

            $changes = [];

            if ((int) $row['active'] !== 1) {
                $changes['active'] = 1;
            }

            if ($row['module'] !== $declaration['module']) {
                $changes['module'] = $declaration['module'];
            }

            if ($row['expression'] !== $expression || $row['next_run_at'] === null) {
                $changes['expression']  = $expression;
                $changes['next_run_at'] = $task->schedule()->nextRun($now)->format(self::FORMAT);
            }

            if ($changes !== []) {
                $this->db()->table('scheduled_tasks')->where('id', $row['id'])->update($changes + ['updated_at' => $stamp]);
            }
        }

        foreach (array_diff_key($stored, $declared) as $row) {
            if ((int) $row['active'] === 1) {
                $this->db()->table('scheduled_tasks')->where('id', $row['id'])->update(['active' => 0, 'updated_at' => $stamp]);
            }
        }
    }

    /**
     * @return list<array<string, mixed>> Every row (active first, then by module and class)
     */
    public function rows(): array
    {
        return $this->db()->table('scheduled_tasks')->orderBy('active', 'desc')->orderBy('module')->orderBy('class')->get()->getResultArray();
    }

    /**
     * A row by id, class name or short class name (case-insensitive).
     *
     * @return array<string, mixed>|null
     */
    public function find(int|string $task): ?array
    {
        if (is_int($task) || ctype_digit($task)) {
            return $this->db()->table('scheduled_tasks')->where('id', (int) $task)->get()->getRowArray();
        }

        $task = ltrim($task, '\\');

        foreach ($this->rows() as $row) {
            $class = (string) $row['class'];
            $short = substr($class, (int) strrpos($class, '\\') + 1);

            if (strcasecmp($class, $task) === 0 || strcasecmp($short, $task) === 0) {
                return $row;
            }
        }

        return null;
    }

    /**
     * The task object of a class, or null when it cannot be loaded (logged once).
     */
    public function instance(string $class): ?TaskInterface
    {
        if (array_key_exists($class, $this->instances)) {
            return $this->instances[$class];
        }

        $task = null;

        try {
            if (! class_exists($class) || ! is_subclass_of($class, TaskInterface::class)) {
                log_message('error', 'Scheduled task {class} cannot be loaded: the class does not exist or does not implement TaskInterface.', ['class' => $class]);
            } else {
                $task = new $class();
                $task->schedule(); // an invalid expression fails here, not in the middle of a run
            }
        } catch (Throwable $e) {
            log_message('error', 'Scheduled task {class} cannot be loaded: {message}', ['class' => $class, 'message' => $e->getMessage()]);
            $task = null;
        }

        return $this->instances[$class] = $task;
    }

    /**
     * Takes the run that is due at `$due` (the current `next_run_at`) by moving `next_run_at`
     * forward in one atomic update; false when another process took it first.
     */
    public function claim(int $id, string $due, DateTimeImmutable $next): bool
    {
        $this->db()->table('scheduled_tasks')->where('id', $id)->where('next_run_at', $due)->update(['next_run_at' => $next->format(self::FORMAT)]);

        return $this->db()->affectedRows() === 1;
    }

    /**
     * Takes the `withoutOverlapping()` lock (free or expired) for `$owner`.
     */
    public function lock(int $id, string $owner, DateTimeImmutable $now, int $minutes): bool
    {
        $stamp = $now->format(self::FORMAT);
        $this->db()->table('scheduled_tasks')
            ->where('id', $id)
            ->groupStart()->where('locked_until', null)->orWhere('locked_until <', $stamp)->groupEnd()
            ->update(['locked_until' => $now->modify("+{$minutes} minutes")->format(self::FORMAT), 'lock_owner' => $owner]);

        return $this->db()->affectedRows() === 1;
    }

    public function unlock(int $id, string $owner): void
    {
        $this->db()->table('scheduled_tasks')->where('id', $id)->where('lock_owner', $owner)->update(['locked_until' => null, 'lock_owner' => null]);
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    public function db(): BaseConnection
    {
        return $this->db ?? Database::connect();
    }
}
