<?php

declare(strict_types=1);

namespace Modules\Core\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Kpcrudy\Crud;
use Modules\Core\Scheduler\ScheduledTasks;
use Modules\Core\Scheduler\TaskResult;
use Modules\Core\Scheduler\TaskRunner;

/**
 * The scheduled tasks screen (resource `schedule`, kpcrudy, read-only): each task's schedule,
 * next run and last result (`schedule.list`), its latest runs with their output
 * (`schedule.show`) and "run now" (`schedule.run`).
 */
final class Schedule extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $runner = new TaskRunner();
        $tasks  = $runner->tasks();
        $tasks->sync(TaskRunner::now());

        $crud = $this->crud('schedule')
            ->setTable('scheduled_tasks')
            ->setSubject(lang('Core.schedule.task'), lang('Core.schedule.title'))
            ->columns(['class', 'module', 'expression', 'next_run_at', 'last_run_at', 'last_status', 'last_duration'])
            ->showFields(['class', 'module', 'expression', 'active', 'next_run_at', 'last_run_at', 'last_status', 'last_duration', 'runs'])
            ->searchFields(['class', 'module'])
            ->filters(['module', 'last_status'])
            ->unsortable(['runs'])
            ->displayAs([
                'class'         => lang('Core.schedule.task'),
                'module'        => lang('Core.schedule.module'),
                'expression'    => lang('Core.schedule.expression'),
                'active'        => lang('Core.schedule.active'),
                'next_run_at'   => lang('Core.schedule.nextRun'),
                'last_run_at'   => lang('Core.schedule.lastRun'),
                'last_status'   => lang('Core.schedule.result'),
                'last_duration' => lang('Core.schedule.duration'),
                'runs'          => lang('Core.schedule.runs'),
            ])
            ->callbackColumn('class', static function ($value) use ($tasks): string {
                $task = $tasks->instance((string) $value);

                return ($task !== null ? esc($task->name()) . '<div class="small text-body-secondary text-break">' : '<div class="text-break">') . esc((string) $value) . '</div>';
            })
            ->callbackColumn('expression', static fn ($value): string => '<code>' . esc((string) $value) . '</code>')
            ->callbackColumn('active', static fn ($value): string => (int) $value === 1
                ? ui_badge(lang('Core.schedule.yes'), 'success')
                : ui_badge(lang('Core.schedule.inactive'), 'secondary'))
            ->callbackColumn('next_run_at', static fn ($value, array $row): string => (int) ($row['active'] ?? 0) === 1 && $value !== null
                ? ui_date((string) $value, 'datetime')
                : ui_badge(lang('Core.schedule.inactive'), 'secondary'))
            ->callbackColumn('last_run_at', static fn ($value): string => $value === null ? '—' : ui_date((string) $value, 'datetime'))
            ->callbackColumn('last_status', static fn ($value): string => self::statusBadge($value === null ? null : (string) $value))
            ->callbackColumn('last_duration', static fn ($value): string => $value === null ? '—' : esc(number_format((int) $value)) . ' ms')
            ->callbackColumn('runs', static fn ($value, array $row): string => self::runsTable((int) ($row['id'] ?? 0)))
            ->defaultOrder('module')
            ->readOnly()->unsetExport()->unsetBulk()
            ->addOperation('run', static function (string $id, array $row, Crud $crud) use ($runner): ResponseInterface {
                try {
                    $run = $runner->runNow((int) $id, TaskRunner::SOURCE_MANUAL, (int) auth()->id());
                } catch (\InvalidArgumentException $e) {
                    return $crud->backToList('danger', $e->getMessage());
                }

                $message = lang('Core.schedule.ran', [$run->name, lang('Core.schedule.status.' . $run->status)]);

                return $crud->backToList($run->status === TaskResult::SUCCESS ? 'success' : ($run->status === TaskResult::FAILED ? 'danger' : 'warning'), $message);
            }, [
                'label'   => lang('Core.schedule.runNow'),
                'icon'    => 'bi bi-play-fill',
                'confirm' => lang('Core.schedule.confirmRun'),
                'visible' => static fn (?array $row): bool => $row === null || (int) ($row['active'] ?? 0) === 1,
            ]);

        return $this->renderCrud($crud);
    }

    private static function statusBadge(?string $status): string
    {
        return match ($status) {
            null                => '—',
            TaskResult::SUCCESS => ui_badge(lang('Core.schedule.status.success'), 'success'),
            TaskResult::FAILED  => ui_badge(lang('Core.schedule.status.failed'), 'danger'),
            default             => ui_badge(lang('Core.schedule.status.skipped'), 'warning'),
        };
    }

    /**
     * The latest runs of a task with their output (detail view).
     */
    private static function runsTable(int $taskId): string
    {
        $runs = (new ScheduledTasks())->db()->table('task_runs')->where('task_id', $taskId)->orderBy('id', 'desc')->limit(20)->get()->getResultArray();

        return view('Modules\Core\Views\schedule\runs', ['runs' => $runs], ['debug' => false]);
    }
}
