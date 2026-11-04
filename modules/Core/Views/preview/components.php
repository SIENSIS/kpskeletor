<?php
/**
 * Theme preview, JavaScript components: a `data-component` with options and a JSON data block,
 * and the core toast, confirm, modal, http (JSON + CSRF) and form behaviours, all under the
 * enforced CSP (no inline code, handlers or style attributes).
 *
 * @var string $layout
 */
$items = [
    ['name' => lang('Core.preview.menu.news'), 'count' => 12],
    ['name' => lang('Core.preview.menu.events'), 'count' => 5],
    ['name' => lang('Core.preview.menu.files'), 'count' => 31],
];
$options = [
    'start'    => 3,
    'dataId'   => 'demo-items',
    'pingUrl'  => base_url('theme-preview/ping'),
    'errorUrl' => base_url('theme-preview/ping-error'),
];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm" data-component="core/demo/showcase" data-options="<?= esc(json_encode($options, JSON_UNESCAPED_SLASHES)) ?>">
            <div class="card-header d-flex align-items-center gap-2"><i class="bi bi-braces" aria-hidden="true"></i><h2 class="h6 mb-0"><?= esc(lang('Core.preview.js.title')) ?></h2></div>
            <div class="card-body">
                <p class="text-body-secondary"><?= esc(lang('Core.preview.js.intro')) ?></p>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="fs-2 fw-semibold" data-demo-count>0</span>
                    <div class="progress demo-progress flex-grow-1" role="progressbar" aria-label="<?= esc(lang('Core.preview.js.progress')) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div class="progress-bar" data-demo-progress></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-primary" data-demo-action="increment"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Core.preview.js.increment')) ?></button>
                    <button type="button" class="btn btn-outline-success" data-demo-action="toast" data-message="<?= esc(lang('Core.preview.js.toastText')) ?>"><?= esc(lang('Core.preview.js.toast')) ?></button>
                    <button type="button" class="btn btn-outline-danger" data-demo-action="confirm" data-message="<?= esc(lang('Core.preview.js.confirmText')) ?>"><?= esc(lang('Core.preview.js.confirm')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-demo-action="modal" data-title="<?= esc(lang('Core.preview.js.modal')) ?>" data-message="<?= esc(lang('Core.preview.js.modalText')) ?>"><?= esc(lang('Core.preview.js.modal')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-demo-action="http"><?= esc(lang('Core.preview.js.http')) ?></button>
                    <button type="button" class="btn btn-outline-secondary" data-demo-action="http-error"><?= esc(lang('Core.preview.js.httpError')) ?></button>
                </div>
                <h3 class="h6"><?= esc(lang('Core.preview.js.data')) ?></h3>
                <ul class="list-group" data-demo-list></ul>
            </div>
        </div>
        <script type="application/json" id="demo-items"><?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    </div>
    <div class="col-lg-5">
        <?= theme_component('form_layout', [
            'action'     => '#',
            'title'      => lang('Core.preview.js.form'),
            'dirtyGuard' => true,
            'fields'     => '<p class="small text-body-secondary">' . esc(lang('Core.preview.js.formIntro')) . '</p>'
                . '<label class="form-label" for="demo-date">' . esc(lang('Core.preview.fieldDate')) . '</label>'
                . '<input type="date" class="form-control" id="demo-date" name="date" data-component="core/forms/date" data-options="' . esc('{"min":"today","max":"+1y"}') . '">',
            'cancelUrl' => '#',
        ]) ?>
        <div class="mt-3">
            <a class="btn btn-outline-danger" href="<?= esc(base_url('theme-preview/components')) ?>" data-confirm="<?= esc(lang('Core.preview.js.linkConfirm')) ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Core.preview.js.link')) ?></a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
