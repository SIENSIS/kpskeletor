<?php
/**
 * Create a backup now (`backup.create`).
 *
 * @var string                $layout
 * @var string                $type
 * @var int                   $minLength
 * @var array<string, string> $errors
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-7">
        <?php ob_start() ?>
        <p class="text-body-secondary"><?= esc(lang('Backup.form.createIntro')) ?></p>
        <fieldset class="mb-3">
            <legend class="form-label fs-6"><?= esc(lang('Backup.field.type')) ?></legend>
            <?php foreach (['full', 'db', 'files'] as $option): ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="type" id="backup-type-<?= $option ?>" value="<?= $option ?>"<?= $type === $option ? ' checked' : '' ?>>
                    <label class="form-check-label" for="backup-type-<?= $option ?>"><?= esc(lang('Backup.type.' . $option)) ?> <span class="text-body-secondary small">— <?= esc(lang('Backup.typeHelp.' . $option)) ?></span></label>
                </div>
            <?php endforeach ?>
        </fieldset>
        <?= view('Modules\Backup\Views\_field', ['name' => 'password', 'label' => lang('Backup.field.password'), 'type' => 'password', 'help' => lang('Backup.form.passwordHelp', [$minLength]), 'autocomplete' => 'new-password', 'errors' => $errors]) ?>
        <?= view('Modules\Backup\Views\_field', ['name' => 'password_confirm', 'label' => lang('Backup.field.passwordConfirm'), 'type' => 'password', 'autocomplete' => 'new-password', 'errors' => $errors]) ?>
        <div class="alert alert-warning mb-0" role="note"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Backup.form.passwordWarning')) ?></div>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => backend_url('backups/create'),
            'fields'      => $fields,
            'submitLabel' => lang('Backup.action.create'),
            'cancelUrl'   => backend_url('backups'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
