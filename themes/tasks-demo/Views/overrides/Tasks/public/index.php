<?php
/**
 * Override of the Tasks public page by the theme "Tasks Demo" (tutorial 9): the same data as
 * `modules/Tasks/Views/public/index.php` (see its variables) drawn as coloured columns, inside the
 * parent theme's public layout. Only this view changes; everything else comes from `default`.
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
<header class="tasks-demo-hero mb-4">
    <?php if ($breadcrumbs === []): ?><h1 class="h2 mb-1"><?= esc($title) ?></h1><?php endif ?>
    <?php if ($intro !== ''): ?><p class="text-body-secondary mb-2"><?= esc($intro) ?></p><?php endif ?>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-sm <?= $current === null ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc(site_url('tasks'), 'attr') ?>"><?= esc(lang('Tasks.public.all')) ?></a>
        <?php foreach ($projects as $project): ?>
            <a class="btn btn-sm <?= $current === $project['slug'] ? 'btn-primary' : 'btn-outline-primary' ?>" href="<?= esc(site_url('tasks/project/' . rawurlencode($project['slug'])), 'attr') ?>"><?= esc($project['name']) ?></a>
        <?php endforeach ?>
        <?php if ($requestUrl !== null): ?>
            <a class="btn btn-sm btn-success ms-auto" href="<?= esc($requestUrl, 'attr') ?>"><i class="bi bi-lightbulb" aria-hidden="true"></i> <?= esc(lang('Tasks.public.request')) ?></a>
        <?php endif ?>
    </div>
</header>
<div class="row g-3 tasks-demo-board">
    <?php foreach ($columns as $status => $items): ?>
        <section class="col-12 col-md-4" aria-label="<?= esc(lang('Tasks.statuses.' . $status), 'attr') ?>">
            <div class="tasks-demo-column tasks-demo-column-<?= esc($status, 'attr') ?>">
                <h2 class="h6 text-uppercase"><?= esc(lang('Tasks.statuses.' . $status)) ?> · <?= count($items) ?></h2>
                <?php if ($items === []): ?>
                    <p class="small text-body-secondary mb-0"><?= esc(lang('Tasks.public.empty')) ?></p>
                <?php endif ?>
                <?php foreach ($items as $item): ?>
                    <article class="card border-0 shadow-sm mb-2">
                        <div class="card-body p-2">
                            <h3 class="h6 mb-1 text-break"><?= esc($item['title']) ?></h3>
                            <?php if ($item['due'] !== null): ?><div class="small text-body-secondary"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= ui_date($item['due'], 'date') ?></div><?php endif ?>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
        </section>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>
