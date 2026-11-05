<?php
/**
 * Logout confirmation (GET logout shows it; the logout itself is a POST with CSRF).
 *
 * @var string $layout
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-center"><?= esc(lang('Auth.ui.logoutQuestion')) ?></p>
<form action="<?= esc(site_url('logout')) ?>" method="post" class="d-grid">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i><?= esc(lang('Auth.ui.logout')) ?></button>
</form>
<?= $this->endSection() ?>
