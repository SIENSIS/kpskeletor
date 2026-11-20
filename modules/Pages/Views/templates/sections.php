<?php
/**
 * Page template of a page in `sections` layout (D-45-01): its visible sections in order over the
 * whole width of the public layout (`fullWidth`: no outer container; each section adds its own).
 * The page title is the page's `<h1>` (visually hidden: the sections carry the visible headings).
 *
 * @var string                                    $layout
 * @var array<string, mixed>                      $page
 * @var string                                    $sections HTML of the sections (SectionRenderer)
 * @var list<array{label: string, url?: string}> $trail
 * @var bool                                      $preview
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if ($preview || $trail !== []): ?>
    <div class="container pt-3">
        <?php if ($preview): ?>
            <div class="alert alert-warning d-flex gap-2 align-items-start" role="status">
                <i class="bi bi-eye" aria-hidden="true"></i>
                <div>
                    <strong><?= esc(lang('Pages.previewNotice')) ?></strong>
                    <?= esc(lang('Pages.previewStatus', [lang('Pages.statuses.' . \Modules\Pages\Services\PageVisibility::displayStatus($page)), lang('Pages.visibilities.' . (string) $page['visibility'])])) ?>
                </div>
            </div>
        <?php endif ?>
        <?php if ($trail !== []): ?>
            <?= theme_component('breadcrumbs', ['items' => $trail]) ?>
        <?php endif ?>
    </div>
<?php endif ?>
<article class="page-sections">
    <h1 class="visually-hidden"><?= esc((string) $page['title']) ?></h1>
    <?php if ($sections === ''): ?>
        <div class="container py-5">
            <p class="text-body-secondary text-center mb-0"><?= esc(lang('Pages.sections.emptyPage')) ?></p>
        </div>
    <?php else: ?>
        <?= $sections ?>
    <?php endif ?>
</article>
<?= $this->endSection() ?>
