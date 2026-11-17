<?php
/**
 * Widget "My pending to-do tasks" (`Modules\Tasks\Widgets\MyPendingTasks`).
 *
 * @var list<array{title: string, status: string, priority: string, due: string|null, overdue: bool}> $items
 * @var int                                                                                         $total
 * @var string                                                                                      $url
 */
?>
<ul class="list-group list-group-flush mb-2">
    <?php foreach ($items as $item): ?>
        <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="fw-semibold text-break"><?= esc($item['title']) ?></div>
                <div class="small text-body-secondary">
                    <?= esc(lang('Tasks.statuses.' . $item['status'])) ?>
                    <?php if ($item['due'] !== null): ?> · <span class="<?= $item['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= ui_date($item['due'], 'date') ?></span><?php endif ?>
                </div>
            </div>
            <?php if ($item['priority'] === 'high'): ?><?= ui_badge(lang('Tasks.priorities.high'), 'danger') ?><?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Tasks.widgets.pending.all', [$total])) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
