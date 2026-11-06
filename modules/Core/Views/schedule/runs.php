<?php
/**
 * The latest runs of a scheduled task (detail view of the scheduled tasks screen).
 *
 * @var list<array<string, mixed>> $runs
 */
?>
<?php if ($runs === []): ?>
    <span class="text-body-secondary"><?= esc(lang('Core.schedule.noRuns')) ?></span>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm align-top mb-0">
        <thead>
            <tr>
                <th scope="col"><?= esc(lang('Core.schedule.started')) ?></th>
                <th scope="col"><?= esc(lang('Core.schedule.result')) ?></th>
                <th scope="col"><?= esc(lang('Core.schedule.duration')) ?></th>
                <th scope="col"><?= esc(lang('Core.schedule.source')) ?></th>
                <th scope="col"><?= esc(lang('Core.schedule.output')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td class="text-nowrap"><?= ui_date((string) $run['started_at'], 'datetime') ?></td>
                    <td><?= ui_badge(lang('Core.schedule.status.' . (in_array($run['status'], ['success', 'failed', 'skipped'], true) ? $run['status'] : 'skipped')), match ($run['status']) {
                        'success' => 'success',
                        'failed'  => 'danger',
                        default   => 'warning',
                    }) ?></td>
                    <td class="text-nowrap"><?= esc(number_format((int) $run['duration'])) ?> ms</td>
                    <td><?= esc(lang('Core.schedule.sources.' . (in_array($run['source'], ['cron', 'manual', 'web'], true) ? $run['source'] : 'cron'))) ?></td>
                    <td><?php if ((string) $run['output'] !== ''): ?><pre class="small mb-0 text-wrap text-break"><?= esc((string) $run['output']) ?></pre><?php endif ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php endif ?>
