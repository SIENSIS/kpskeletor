<?php
/**
 * Confirms the external probe (`security.check` + re-authentication): requests from the server
 * to the site's own URL.
 *
 * @var string $layout
 * @var string $action
 * @var string $cancel
 * @var string $url
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <p><?= esc(lang('SecurityCheck.probe.intro')) ?> <code><?= esc($url) ?></code></p>
        <ul class="small">
            <li><?= esc(lang('SecurityCheck.probe.headers')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.hidden')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.uploads')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.redirect')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.login')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.https')) ?></li>
            <li><?= esc(lang('SecurityCheck.probe.progress')) ?></li>
        </ul>
        <p class="small text-body-secondary"><?= esc(lang('SecurityCheck.probe.note')) ?></p>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => $action,
            'fields'      => $fields,
            'submitLabel' => lang('SecurityCheck.probe.submit'),
            'cancelUrl'   => $cancel,
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
