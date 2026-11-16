<?php
/**
 * Maintenance page (503, Modules\Core\Filters\Maintenance through MaintenancePage) in the active
 * theme's blank layout, like the other error pages; a theme overrides it in
 * `themes/<slug>/Views/errors/maintenance.php`. The database may be being replaced: settings
 * answer the file configuration only (MaintenancePage), and this view must not read anything
 * else (no models, no session data).
 *
 * @var string      $message the administrator's plain-text message (empty = the default text)
 * @var string|null $retry   the URL to try again
 */
?>
<?= $this->extend(theme_layout('blank')) ?>
<?= $this->section('content') ?>
<main class="error-page maintenance-page container py-5 text-center">
    <i class="bi bi-cone-striped error-page-icon text-warning" aria-hidden="true"></i>
    <p class="display-4 fw-semibold mt-3 mb-0">503</p>
    <h1 class="h3"><?= esc(lang('Core.maintenance.title')) ?></h1>
    <p class="text-body-secondary mx-auto maintenance-message"><?= esc(($message ?? '') !== '' ? $message : lang('Core.maintenance.message')) ?></p>
    <a href="<?= esc(ui_safe_url($retry ?? base_url('/'))) ?>" class="btn btn-primary mt-2"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><?= esc(lang('Core.maintenance.retry')) ?></a>
</main>
<?= $this->endSection() ?>
