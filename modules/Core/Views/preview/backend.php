<?php
/**
 * Theme preview, back office: every standard view and component with sample content.
 *
 * @var string                                  $layout
 * @var list<array{slug: string, name: string}> $themes
 */
$fields = '<div class="row g-3">'
    . '<div class="col-md-6"><label class="form-label" for="preview-title">' . esc(lang('Core.preview.fieldTitle')) . '</label>'
    . '<input type="text" class="form-control" id="preview-title" name="title" value="' . esc(lang('Core.preview.sampleTitle')) . '"></div>'
    . '<div class="col-md-6"><label class="form-label" for="preview-date">' . esc(lang('Core.preview.fieldDate')) . '</label>'
    . '<input type="date" class="form-control" id="preview-date" name="date" value="2026-11-04"></div>'
    . '<div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="preview-published" checked>'
    . '<label class="form-check-label" for="preview-published">' . esc(lang('Core.preview.fieldPublished')) . '</label></div></div></div>';

$table = '<table class="table table-hover align-middle mb-0"><thead><tr>'
    . '<th scope="col">' . esc(lang('Core.preview.colTitle')) . '</th><th scope="col">' . esc(lang('Core.preview.colAuthor')) . '</th>'
    . '<th scope="col">' . esc(lang('Core.preview.colStatus')) . '</th><th scope="col">' . esc(lang('Core.preview.colDate')) . '</th></tr></thead><tbody>'
    . '<tr><td>' . esc(lang('Core.preview.sampleTitle')) . '</td><td>' . ui_user_link('Anna Puig', '#') . '</td><td>' . ui_status('published', lang('Core.preview.statusPublished')) . '</td><td>' . ui_date('2026-11-02 10:30:00') . '</td></tr>'
    . '<tr><td>' . esc(lang('Core.preview.sampleTitle2')) . '</td><td>' . ui_user_link('Marc Soler', '#') . '</td><td>' . ui_status('draft', lang('Core.preview.statusDraft')) . '</td><td>' . ui_date('2026-10-28 09:00:00') . '</td></tr>'
    . '<tr><td>' . esc(lang('Core.preview.sampleTitle3')) . '</td><td>' . ui_user_link('Laia Vidal', '#') . '</td><td>' . ui_status('pending', lang('Core.preview.statusPending')) . '</td><td>' . ui_date('2026-10-15 16:45:00') . '</td></tr>'
    . '</tbody></table>';
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="alert alert-success alert-dismissible fade show" role="status">
    <?= esc(lang('Core.preview.flash')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('Core.ui.close')) ?>"></button>
</div>
<div class="row g-4">
    <div class="col-12">
        <?= theme_component('crud_container', ['title' => lang('Core.preview.crud'), 'resource' => 'preview', 'content' => $table . '<div class="mt-3">' . ui_pagination_info(1, 3, 3) . '</div>']) ?>
    </div>
    <div class="col-lg-7">
        <?= theme_component('form_layout', [
            'action'    => '#',
            'title'     => lang('Core.preview.form'),
            'fields'    => $fields,
            'cancelUrl' => '#',
        ]) ?>
    </div>
    <div class="col-lg-5">
        <?= theme_component('card', [
            'title'   => lang('Core.preview.components'),
            'icon'    => 'bi-puzzle',
            'body'    => '<div class="d-flex flex-wrap gap-2 mb-3">' . ui_badge(lang('Core.preview.badge'), 'primary') . ui_badge(lang('Core.preview.badge'), 'success', 'bi-check') . ui_badge(lang('Core.preview.badge'), 'warning') . ui_badge(lang('Core.preview.badge'), 'danger') . '</div>'
                . '<div class="d-flex flex-wrap gap-2 mb-3">' . ui_status('active', lang('Core.preview.statusActive')) . ui_status('error', lang('Core.preview.statusError')) . ui_status('archived', lang('Core.preview.statusArchived')) . '</div>'
                . '<div class="d-flex align-items-center gap-2 mb-3">' . ui_avatar('Anna Puig', null, 'sm') . ui_avatar('Marc Soler') . ui_avatar('Laia Vidal', null, 'lg') . '</div>'
                . '<ul class="list-unstyled mb-0"><li>' . ui_file_link('memoria-2026.pdf', '#', 248_000) . '</li><li>' . ui_link(lang('Core.preview.externalLink'), 'https://getbootstrap.com/') . '</li><li>' . ui_date('2026-11-04 08:15:00', 'long') . '</li></ul>',
            'footer'  => '<small class="text-body-secondary">' . esc(lang('Core.preview.chain', [implode(' → ', array_column($themes, 'slug'))])) . '</small>',
        ]) ?>
    </div>
    <div class="col-lg-7">
        <?= theme_component('tabs', ['id' => 'preview-tabs', 'tabs' => [
            ['id' => 'general', 'label' => lang('Core.preview.tabGeneral'), 'icon' => 'bi-card-text', 'content' => '<p class="mb-0">' . esc(lang('Core.preview.tabText')) . '</p>'],
            ['id' => 'history', 'label' => lang('Core.preview.tabHistory'), 'icon' => 'bi-clock-history', 'content' => '<p class="mb-0">' . esc(lang('Core.preview.tabText')) . '</p>'],
        ]]) ?>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <?= theme_component('empty_state', ['icon' => 'bi-calendar-x', 'title' => lang('Core.preview.emptyTitle'), 'message' => lang('Core.preview.emptyText'), 'action' => ['label' => lang('Core.preview.add'), 'url' => '#', 'icon' => 'bi-plus-lg']]) ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
