<?php
/**
 * Widget "Scheduled tasks" (`Modules\Core\Widgets\ScheduledTasks`).
 *
 * @var list<array{name: string, module: string, lastRun: string|null}> $items
 * @var int                                                             $failed
 * @var int                                                             $total
 * @var string|null                                                     $next
 * @var string                                                          $url
 */
?>
<?php if ($failed === 0): ?>
    <p class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
        <span><?= esc(lang('Core.widgets.scheduledTasks.allOk', [$total])) ?></span>
    </p>
<?php else: ?>
    <p class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-exclamation-octagon-fill text-danger" aria-hidden="true"></i>
        <span><?= esc(lang('Core.widgets.scheduledTasks.failing', [$failed])) ?></span>
    </p>
    <ul class="list-group list-group-flush mb-2">
        <?php foreach ($items as $item): ?>
            <li class="list-group-item px-0">
                <div class="fw-semibold"><?= esc($item['name']) ?></div>
                <div class="small text-body-secondary">
                    <?= esc($item['module']) ?> · <?= esc(lang('Core.widgets.scheduledTasks.lastRun')) ?>
                    <?= $item['lastRun'] === null ? '—' : ui_date($item['lastRun'], 'datetime') ?>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
<?php if ($next !== null): ?>
    <p class="small text-body-secondary mb-2"><?= esc(lang('Core.widgets.scheduledTasks.nextRun')) ?> <?= ui_date($next, 'datetime') ?></p>
<?php endif ?>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Core.widgets.scheduledTasks.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
