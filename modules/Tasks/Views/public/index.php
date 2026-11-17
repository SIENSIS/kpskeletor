<?php
/**
 * Public page of the to-do tasks (tutorial 5): public to-do tasks by status, the projects as
 * filters and the link to the request form. A theme overrides it in
 * `themes/<slug>/Views/overrides/Tasks/public/index.php` (tutorial 9: `themes/tasks-demo`).
 *
 * @var string                                                   $layout
 * @var string                                                   $title
 * @var string                                                   $intro
 * @var array<string, list<array<string, mixed>>>                $columns
 * @var list<array{slug: string, name: string, color: string}>   $projects
 * @var string|null                                              $current
 * @var string|null                                              $requestUrl
 * @var list<array<string, mixed>>                               $breadcrumbs (when given, the layout prints the title)
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <?php if ($breadcrumbs === []): ?><h1 class="h2 mb-0"><?= esc($title) ?></h1><?php else: ?><span></span><?php endif ?>
    <?php if ($requestUrl !== null): ?>
        <a class="btn btn-sm btn-primary" href="<?= esc($requestUrl, 'attr') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> <?= esc(lang('Tasks.public.request')) ?></a>
    <?php endif ?>
</div>
<?php if ($intro !== ''): ?>
    <p class="lead text-body-secondary"><?= nl2br(esc($intro)) ?></p>
<?php endif ?>
<?php if ($projects !== []): ?>
    <nav class="mb-4" aria-label="<?= esc(lang('Tasks.projects'), 'attr') ?>">
        <ul class="nav nav-pills small gap-1 flex-wrap">
            <li class="nav-item"><a class="nav-link<?= $current === null ? ' active' : '' ?>" href="<?= esc(site_url('tasks'), 'attr') ?>"<?= $current === null ? ' aria-current="page"' : '' ?>><?= esc(lang('Tasks.public.all')) ?></a></li>
            <?php foreach ($projects as $project): ?>
                <li class="nav-item"><a class="nav-link<?= $current === $project['slug'] ? ' active' : '' ?>" href="<?= esc(site_url('tasks/project/' . rawurlencode($project['slug'])), 'attr') ?>"<?= $current === $project['slug'] ? ' aria-current="page"' : '' ?>><?= esc($project['name']) ?></a></li>
            <?php endforeach ?>
        </ul>
    </nav>
<?php endif ?>
<?php if (array_sum(array_map('count', $columns)) === 0): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-kanban', 'title' => lang('Tasks.public.empty'), 'message' => null, 'action' => null, 'compact' => false]) ?>
<?php else: ?>
    <div class="row g-4 tasks-public">
        <?php foreach ($columns as $status => $items): ?>
            <section class="col-12 col-md-4" aria-labelledby="tasks-public-<?= esc($status, 'attr') ?>">
                <h2 class="h5" id="tasks-public-<?= esc($status, 'attr') ?>"><?= esc(lang('Tasks.statuses.' . $status)) ?> <span class="badge text-bg-secondary"><?= count($items) ?></span></h2>
                <?php foreach ($items as $item): ?>
                    <article class="card mb-2 shadow-sm">
                        <div class="card-body p-3">
                            <h3 class="h6 card-title text-break"><?= esc($item['title']) ?></h3>
                            <?php if ($item['description'] !== ''): ?>
                                <p class="card-text small text-body-secondary mb-2"><?= esc(mb_strimwidth($item['description'], 0, 200, '…')) ?></p>
                            <?php endif ?>
                            <div class="d-flex flex-wrap gap-1 small">
                                <?php if ($item['due'] !== null): ?><span class="text-body-secondary"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= ui_date($item['due'], 'date') ?></span><?php endif ?>
                                <?php foreach ($item['labels'] as $label): ?><span class="badge rounded-pill text-bg-light border"><?= esc(lang('Tasks.labels.' . $label)) ?></span><?php endforeach ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach ?>
            </section>
        <?php endforeach ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
