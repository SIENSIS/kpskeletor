<?php
/**
 * Upload a backup file to restore it (`backup.restore` + re-authentication).
 *
 * @var string                $layout
 * @var array<string, string> $errors
 */
$error = $errors['backup'] ?? null;
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <p class="text-body-secondary"><?= esc(lang('Backup.form.uploadIntro')) ?></p>
        <div class="mb-3">
            <label for="backup-file" class="form-label"><?= esc(lang('Backup.field.file')) ?></label>
            <input type="file" class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="backup-file" name="backup" accept=".kpbackup" required>
            <?php if ($error !== null): ?><div class="invalid-feedback"><?= esc($error) ?></div><?php endif ?>
        </div>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => backend_url('backups/upload'),
            'fields'      => $fields,
            'multipart'   => true,
            'submitLabel' => lang('Backup.action.upload'),
            'cancelUrl'   => backend_url('backups'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
