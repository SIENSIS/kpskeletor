<?php
/**
 * Upload a module or theme package (`modules.upload` + re-authentication). It is validated and
 * kept in writable/packages until a superadmin approves it and the console installs it.
 *
 * @var string                     $layout
 * @var string|null                $error
 * @var \Modules\Core\Config\Packages $config
 */
helper('number');
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <p class="text-body-secondary small"><?= esc(lang('Core.packages.uploadIntro', [number_to_size($config->maxPackageBytes)])) ?></p>
        <div class="mb-3">
            <label for="package-file" class="form-label"><?= esc(lang('Core.packages.zip')) ?></label>
            <input type="file" class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="package-file" name="package" accept=".zip,application/zip" required>
            <?php if ($error !== null): ?><div class="invalid-feedback" data-upload-error><?= nl2br(esc($error)) ?></div><?php endif ?>
        </div>
        <div class="mb-3">
            <label for="package-signature" class="form-label"><?= esc(lang('Core.packages.signatureFile')) ?></label>
            <input type="file" class="form-control" id="package-signature" name="signature" accept=".minisig">
            <div class="form-text"><?= esc(lang($config->requireSignature ? 'Core.packages.signatureRequiredHelp' : 'Core.packages.signatureHelp')) ?></div>
        </div>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => backend_url('modules/packages/upload'),
            'fields'      => $fields,
            'multipart'   => true,
            'submitLabel' => lang('Core.packages.upload'),
            'cancelUrl'   => backend_url('modules/packages'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
