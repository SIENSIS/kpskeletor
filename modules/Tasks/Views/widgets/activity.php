<?php
/**
 * Widget "To-do task activity" (`Modules\Tasks\Widgets\TaskActivityChart`): an SVG bar chart
 * (two bars a day). Sizes and colours are SVG attributes and Bootstrap classes, never `style=""`.
 *
 * @var list<array{date: string, created: int, completed: int}> $days
 * @var int                                                     $max
 * @var int                                                     $created
 * @var int                                                     $completed
 */
$width  = max(1, count($days)) * 12;
$height = 60;
?>
<p class="small mb-2">
    <span class="text-body-secondary"><i class="bi bi-square-fill" aria-hidden="true"></i> <?= esc(lang('Tasks.widgets.activity.created', [$created])) ?></span>
    · <span class="text-success"><i class="bi bi-square-fill" aria-hidden="true"></i> <?= esc(lang('Tasks.widgets.activity.completed', [$completed])) ?></span>
</p>
<svg class="w-100 tasks-activity-chart" viewBox="0 0 <?= $width ?> <?= $height ?>" preserveAspectRatio="none" height="80" role="img" aria-label="<?= esc(lang('Tasks.widgets.activity.summary', [count($days), $created, $completed]), 'attr') ?>">
    <?php foreach ($days as $i => $day): ?>
        <?php $c = (int) round($day['created'] / $max * ($height - 2)); $d = (int) round($day['completed'] / $max * ($height - 2)); ?>
        <g>
            <title><?= esc($day['date'] . ': ' . lang('Tasks.widgets.activity.created', [$day['created']]) . ', ' . lang('Tasks.widgets.activity.completed', [$day['completed']])) ?></title>
            <rect class="text-body-secondary" fill="currentColor" x="<?= $i * 12 + 1 ?>" y="<?= $height - $c ?>" width="4" height="<?= $c ?>"></rect>
            <rect class="text-success" fill="currentColor" x="<?= $i * 12 + 6 ?>" y="<?= $height - $d ?>" width="4" height="<?= $d ?>"></rect>
        </g>
    <?php endforeach ?>
</svg>
