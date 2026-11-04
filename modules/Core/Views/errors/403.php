<?php
/**
 * Error page 403 in the active theme's blank layout. Data: $message (optional, already safe to
 * show: never an exception message in production), $homeUrl.
 *
 * @var string|null $message
 * @var string|null $homeUrl
 */
?>
<?= $this->extend(theme_layout('blank')) ?>
<?= $this->section('content') ?>
<main class="error-page container py-5 text-center">
    <i class="bi bi-shield-lock error-page-icon text-body-tertiary" aria-hidden="true"></i>
    <p class="display-4 fw-semibold mt-3 mb-0">403</p>
    <h1 class="h3"><?= esc(lang('Core.errors.forbidden.title')) ?></h1>
    <p class="text-body-secondary"><?= esc($message ?? lang('Core.errors.forbidden.message')) ?></p>
    <a href="<?= esc(ui_safe_url($homeUrl ?? base_url('/'))) ?>" class="btn btn-primary mt-2"><i class="bi bi-house me-1" aria-hidden="true"></i><?= esc(lang('Core.errors.home')) ?></a>
</main>
<?= $this->endSection() ?>
