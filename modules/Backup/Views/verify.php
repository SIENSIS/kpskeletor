<?php
/**
 * Verify a backup with its password (`backup.list`); after success, its manifest.
 *
 * @var string                    $layout
 * @var string                    $code
 * @var array<string, mixed>      $row
 * @var array<string, mixed>|null $manifest
 * @var array<string, string>     $errors
 */
$manifest ??= null;
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-lg-6">
        <?php ob_start() ?>
        <p><code class="text-break"><?= esc((string) $row['file']) ?></code> · <?= esc(ui_bytes((int) $row['size'])) ?></p>
        <p class="text-body-secondary small"><?= esc(lang('Backup.form.verifyIntro')) ?></p>
        <?= view('Modules\Backup\Views\_field', ['name' => 'password', 'label' => lang('Backup.field.password'), 'type' => 'password', 'autocomplete' => 'off', 'errors' => $errors]) ?>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => site_url('backend/backups/verify/' . $code),
            'fields'      => $fields,
            'submitLabel' => lang('Backup.action.verify'),
            'cancelUrl'   => site_url('backend/backups'),
        ]) ?>
    </div>
    <?php if ($manifest !== null): ?>
        <div class="col-lg-6">
            <?php ob_start() ?>
            <div class="alert alert-success" role="status"><i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= esc(lang('Backup.msg.verified')) ?></div>
            <?= view('Modules\Backup\Views\_manifest', ['manifest' => $manifest]) ?>
            <?php $body = (string) ob_get_clean() ?>
            <?= theme_component('card', ['title' => lang('Backup.form.contents'), 'icon' => 'bi bi-list-check', 'body' => $body]) ?>
        </div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
