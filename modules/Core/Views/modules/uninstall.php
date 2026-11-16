<?php
/**
 * Confirms an uninstall request (`modules.uninstall` + re-authentication): it becomes a pending
 * operation run from the console (`php spark modules:install --pending`).
 *
 * @var string               $layout
 * @var array<string, mixed> $module
 * @var string               $action
 * @var string               $cancel
 * @var bool                 $keepData
 * @var string|null          $error
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert" data-uninstall-error><?= esc($error) ?></div>
        <?php endif ?>
        <p><?= esc(lang('Core.modules.uninstallIntro', [$module['name'] . ' ' . ($module['installed'] ?? $module['version'] ?? '')])) ?></p>
        <ul class="small">
            <li><?= esc(lang('Core.modules.uninstallSteps.backup')) ?></li>
            <li><?= esc(lang('Core.modules.uninstallSteps.remove')) ?></li>
            <li><?= esc(lang('Core.modules.uninstallSteps.console')) ?> <code>php spark modules:install --pending</code></li>
        </ul>
        <?php if ($keepData): ?>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="keep_data" value="1" id="keep-data">
                <label class="form-check-label" for="keep-data"><?= esc(lang('Core.modules.keepData')) ?></label>
            </div>
        <?php endif ?>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => $action,
            'fields'      => $fields,
            'submitLabel' => lang('Core.modules.requestUninstall'),
            'cancelUrl'   => $cancel,
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
