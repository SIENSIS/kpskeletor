<?php
/**
 * The back-office home: the user's widgets (already rendered as grid columns by the Core's
 * WidgetRenderer). Component `dashboard/dashboard`: "Customise" turns on drag and drop (pointer:
 * mouse and touch; keyboard: arrow keys on the handle), resizing and hiding, saved at once.
 * Without JavaScript, "Customise" opens the form page.
 *
 * @var string                                  $layout
 * @var list<string>                            $cards   HTML of each card (escaped by the renderer)
 * @var list<array{id: string, title: string}>  $hidden  Widgets the user hid
 * @var bool                                    $ownLayout
 * @var array<string, string>                   $sizes   size => label
 * @var string                                  $saveUrl
 * @var string                                  $resetUrl
 */
$columns = [];

foreach (array_keys($sizes) as $size) {
    $columns[$size] = \Modules\Core\Widgets\WidgetSizes::columns($size);
}

$config = [
    'saveUrl' => $saveUrl,
    'sizes'   => $sizes,
    'columns' => $columns,
    'texts'   => [
        'move'    => lang('Dashboard.js.move'),
        'size'    => lang('Dashboard.js.size'),
        'hide'    => lang('Dashboard.js.hide'),
        'show'    => lang('Dashboard.js.show'),
        'saved'   => lang('Dashboard.js.saved'),
        'moved'   => lang('Dashboard.js.moved'),
    ],
];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="dashboard" data-component="dashboard/dashboard" data-options-id="dashboard-config">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 dashboard-bar">
        <a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url('backend/dashboard/customize'), 'attr') ?>" data-dashboard-edit aria-pressed="false">
            <i class="bi bi-arrows-move me-1" aria-hidden="true"></i><span data-dashboard-edit-label data-done="<?= esc(lang('Dashboard.done'), 'attr') ?>"><?= esc(lang('Dashboard.customize')) ?></span>
        </a>
        <?php if ($ownLayout): ?>
            <form method="post" action="<?= esc($resetUrl, 'attr') ?>" class="d-inline" data-confirm="<?= esc(lang('Dashboard.restoreConfirm'), 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-link text-decoration-none"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Dashboard.restore')) ?></button>
            </form>
        <?php endif ?>
        <span class="small text-body-secondary ms-auto" data-dashboard-status role="status" aria-live="polite"></span>
    </div>

    <p class="alert alert-info small d-none" data-dashboard-help><?= esc(lang('Dashboard.editHelp')) ?></p>

    <?php if ($cards === []): ?>
        <?= theme_component('empty_state', [
            'icon'    => 'bi bi-speedometer2',
            'title'   => lang('Dashboard.empty'),
            'message' => $hidden === [] ? lang('Dashboard.emptyHelp') : lang('Dashboard.emptyHidden'),
        ]) ?>
    <?php endif ?>

    <div class="row g-3 dashboard-grid" data-dashboard-grid>
        <?php foreach ($cards as $card): ?>
            <?= $card ?>
        <?php endforeach ?>
    </div>

    <section class="mt-4<?= $hidden === [] ? ' d-none' : '' ?>" data-dashboard-hidden aria-labelledby="dashboard-hidden-title">
        <h2 class="h6 text-body-secondary" id="dashboard-hidden-title"><?= esc(lang('Dashboard.hiddenTitle')) ?></h2>
        <ul class="list-inline mb-0" data-dashboard-hidden-list>
            <?php foreach ($hidden as $item): ?>
                <li class="list-inline-item mb-2" data-hidden-widget="<?= esc($item['id'], 'attr') ?>">
                    <span class="badge text-bg-light border fw-normal py-2"><?= esc($item['title']) ?></span>
                </li>
            <?php endforeach ?>
        </ul>
        <p class="small text-body-secondary mb-0" data-dashboard-hidden-help><?= esc(lang('Dashboard.hiddenHelp')) ?></p>
    </section>
</div>
<script type="application/json" id="dashboard-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?= $this->endSection() ?>
