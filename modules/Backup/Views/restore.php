<?php
/**
 * Restore a backup (`backup.restore` + re-authentication): password, parts, typed confirmation.
 *
 * @var string                $layout
 * @var string                $code
 * @var string                $only
 * @var array<string, mixed>  $row
 * @var array<string, string> $errors
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-8">
        <div class="alert alert-danger" role="note">
            <h2 class="h6"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i><?= esc(lang('Backup.form.restoreWarningTitle')) ?></h2>
            <p class="mb-0"><?= esc(lang('Backup.form.restoreWarning')) ?></p>
        </div>
        <?php ob_start() ?>
        <p><code class="text-break"><?= esc((string) $row['file']) ?></code> · <?= esc(ui_bytes((int) $row['size'])) ?> · <?= ui_date((string) $row['created_at'], 'datetime') ?></p>
        <?= view('Modules\Backup\Views\_field', ['name' => 'password', 'label' => lang('Backup.field.password'), 'type' => 'password', 'autocomplete' => 'off', 'errors' => $errors]) ?>
        <fieldset class="mb-3">
            <legend class="form-label fs-6"><?= esc(lang('Backup.form.parts')) ?></legend>
            <?php foreach (['all', 'db', 'files'] as $option): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="only" id="backup-only-<?= $option ?>" value="<?= $option ?>"<?= $only === $option ? ' checked' : '' ?>>
                    <label class="form-check-label" for="backup-only-<?= $option ?>"><?= esc(lang('Backup.parts.' . $option)) ?></label>
                </div>
            <?php endforeach ?>
            <?php if (isset($errors['only'])): ?><div class="text-danger small"><?= esc($errors['only']) ?></div><?php endif ?>
        </fieldset>
        <?= view('Modules\Backup\Views\_field', ['name' => 'confirm', 'label' => lang('Backup.form.typeConfirm', [(string) $row['file']]), 'type' => 'text', 'autocomplete' => 'off', 'errors' => $errors]) ?>
        <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Backup.form.restoreSteps')) ?></p>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => site_url('backend/backups/recover/' . $code),
            'fields'      => $fields,
            'submitLabel' => lang('Backup.action.restore'),
            'cancelUrl'   => site_url('backend/backups'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
