<?php
/**
 * The section editor of a page (`backend/pages/<id>/sections`): the layout switch, the toolbar
 * and the ordered list of sections (`pages/section-list`: drag and drop and keyboard; the up/down
 * buttons also work without JavaScript, one move per request).
 *
 * @var string                     $layout
 * @var array<string, mixed>       $page
 * @var list<array{section: \Modules\Core\Sections\SectionData, type: \Modules\Core\Sections\SectionType|null, summary: string, warnings: list<array{message: string, link?: array{url: string, label: string}}>}> $rows
 * @var string                     $baseUrl
 * @var string                     $previewUrl
 * @var string                     $editUrl
 * @var string                     $revisionsUrl
 * @var bool                       $full
 */
$sectionsMode = ($page['layout'] ?? 'article') === 'sections';
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form method="post" action="<?= esc($baseUrl . '/layout', 'attr') ?>" class="alert <?= $sectionsMode ? 'alert-success' : 'alert-info' ?> d-flex flex-wrap align-items-center gap-2" role="status">
    <?= csrf_field() ?>
    <i class="bi <?= $sectionsMode ? 'bi-check-circle' : 'bi-info-circle' ?>" aria-hidden="true"></i>
    <span class="me-auto"><?= esc(lang($sectionsMode ? 'Pages.sections.modeSections' : 'Pages.sections.modeArticle')) ?></span>
    <input type="hidden" name="layout" value="<?= $sectionsMode ? 'article' : 'sections' ?>">
    <button type="submit" class="btn btn-sm <?= $sectionsMode ? 'btn-outline-secondary' : 'btn-primary' ?>" data-confirm="<?= esc(lang($sectionsMode ? 'Pages.sections.useArticleConfirm' : 'Pages.sections.useSectionsConfirm'), 'attr') ?>">
        <?= esc(lang($sectionsMode ? 'Pages.sections.useArticle' : 'Pages.sections.useSections')) ?>
    </button>
</form>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if (! $full): ?>
        <a class="btn btn-primary" href="<?= esc($baseUrl . '/add', 'attr') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.add')) ?></a>
    <?php endif ?>
    <a class="btn btn-outline-secondary" href="<?= esc($previewUrl, 'attr') ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1" aria-hidden="true"></i><?= esc(lang('Pages.preview')) ?><span class="visually-hidden"> <?= esc(lang('Pages.sections.newTab')) ?></span></a>
    <a class="btn btn-outline-secondary" href="<?= esc($editUrl, 'attr') ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.editPage')) ?></a>
    <a class="btn btn-outline-secondary" href="<?= esc($revisionsUrl, 'attr') ?>"><i class="bi bi-clock-history me-1" aria-hidden="true"></i><?= esc(lang('Pages.revisions')) ?></a>
</div>
<?php if ($rows === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-layout-text-window-reverse', 'title' => lang('Pages.sections.none'), 'message' => lang('Pages.sections.noneHelp')]) ?>
<?php else: ?>
    <form method="post" action="<?= esc($baseUrl . '/order', 'attr') ?>" class="card shadow-sm" data-component="pages/section-list" data-moved-text="<?= esc(lang('Pages.sections.moved'), 'attr') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="order" value="" data-order-input>
        <div class="card-body">
            <p class="small text-body-secondary"><?= esc(lang('Pages.sections.listHelp')) ?></p>
            <ol class="list-unstyled mb-0 kp-section-list" data-section-list>
                <?php foreach ($rows as $position => $row): ?>
                    <?php
                    $section = $row['section'];
                    $type    = $row['type'];
                    $id      = (int) $section->id;
                    $label   = $type?->label() ?? $section->type;
                    ?>
                    <li class="kp-section-row card mb-2<?= $section->hidden ? ' kp-section-row-hidden' : '' ?>" id="section-<?= $id ?>" data-section-item data-id="<?= $id ?>" data-label="<?= esc($label, 'attr') ?>">
                        <div class="card-body d-flex flex-wrap align-items-center gap-2 py-2">
                            <button type="button" class="btn btn-sm btn-light kp-section-handle" data-section-handle aria-label="<?= esc(lang('Pages.sections.handle', [$label, $position + 1]), 'attr') ?>" title="<?= esc(lang('Pages.sections.handleHelp'), 'attr') ?>" hidden><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
                            <span class="kp-section-row-icon" aria-hidden="true"><i class="<?= esc($type?->icon() ?? 'bi bi-question-square', 'attr') ?>"></i></span>
                            <span class="flex-grow-1 min-w-0">
                                <strong class="d-block"><?= esc($label) ?></strong>
                                <?php if ($row['summary'] !== ''): ?>
                                    <span class="small text-body-secondary d-block text-truncate"><?= esc($row['summary']) ?></span>
                                <?php endif ?>
                                <?php if ($section->hidden): ?>
                                    <?= ui_badge(lang('Pages.sections.hidden'), 'secondary', 'bi bi-eye-slash') ?>
                                <?php endif ?>
                                <?php foreach ($row['warnings'] as $warning): ?>
                                    <span class="d-block small text-warning-emphasis mt-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc($warning['message']) ?><?php if (isset($warning['link'])): ?> <a href="<?= esc($warning['link']['url'], 'attr') ?>"><?= esc($warning['link']['label']) ?></a><?php endif ?></span>
                                <?php endforeach ?>
                                <?php if ($type === null): ?>
                                    <?= ui_badge(lang('Pages.sections.unavailable'), 'warning', 'bi bi-exclamation-triangle') ?>
                                <?php endif ?>
                            </span>
                            <div class="btn-group btn-group-sm" role="group" aria-label="<?= esc(lang('Pages.sections.moves', [$label]), 'attr') ?>">
                                <button type="submit" class="btn btn-outline-secondary" name="move" value="<?= $id ?>:up" data-move="up" title="<?= esc(lang('Pages.sections.up'), 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.up') . ': ' . $label, 'attr') ?>"<?= $position === 0 ? ' disabled' : '' ?>><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
                                <button type="submit" class="btn btn-outline-secondary" name="move" value="<?= $id ?>:down" data-move="down" title="<?= esc(lang('Pages.sections.down'), 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.down') . ': ' . $label, 'attr') ?>"<?= $position === count($rows) - 1 ? ' disabled' : '' ?>><i class="bi bi-arrow-down" aria-hidden="true"></i></button>
                            </div>
                            <?php if ($type !== null): ?>
                                <a class="btn btn-sm btn-outline-primary" href="<?= esc($baseUrl . '/' . $id, 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.editOne', [$label]), 'attr') ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.editShort')) ?></a>
                            <?php endif ?>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?= esc($baseUrl . '/' . $id . '/duplicate', 'attr') ?>" title="<?= esc(lang('Pages.sections.duplicate'), 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.duplicate') . ': ' . $label, 'attr') ?>"<?= $full ? ' disabled' : '' ?>><i class="bi bi-copy" aria-hidden="true"></i></button>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" formaction="<?= esc($baseUrl . '/' . $id . '/hide', 'attr') ?>" name="hidden" value="<?= $section->hidden ? '0' : '1' ?>" title="<?= esc(lang($section->hidden ? 'Pages.sections.show' : 'Pages.sections.hide'), 'attr') ?>" aria-label="<?= esc(lang($section->hidden ? 'Pages.sections.show' : 'Pages.sections.hide') . ': ' . $label, 'attr') ?>"><i class="bi <?= $section->hidden ? 'bi-eye' : 'bi-eye-slash' ?>" aria-hidden="true"></i></button>
                            <button type="submit" class="btn btn-sm btn-outline-danger" formaction="<?= esc($baseUrl . '/' . $id . '/delete', 'attr') ?>" data-confirm="<?= esc(lang('Pages.sections.deleteConfirm', [$label]), 'attr') ?>" title="<?= esc(lang('Pages.sections.delete'), 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.delete') . ': ' . $label, 'attr') ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </div>
                    </li>
                <?php endforeach ?>
            </ol>
            <p class="visually-hidden" aria-live="polite" data-section-status></p>
        </div>
        <div class="card-footer d-none" data-order-save>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.saveOrder')) ?></button>
            <span class="small text-body-secondary ms-2"><?= esc(lang('Pages.sections.unsavedOrder')) ?></span>
        </div>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
