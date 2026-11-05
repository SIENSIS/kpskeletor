<?php
/**
 * New password form reached from a reset link (auth layout).
 *
 * @var string $layout
 * @var string $token
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<form action="<?= esc(site_url('reset-password/new')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= esc($token) ?>">
    <div class="mb-3">
        <label for="password" class="form-label"><?= esc(lang('Auth.profile.newPassword')) ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password" autofocus aria-describedby="password-help">
        <div id="password-help" class="form-text"><?= esc(lang('Auth.profile.passwordHelp', [(int) config('Auth')->minimumPasswordLength])) ?></div>
    </div>
    <div class="mb-3">
        <label for="password_confirm" class="form-label"><?= esc(lang('Auth.profile.confirmPassword')) ?></label>
        <input type="password" class="form-control" id="password_confirm" name="password_confirm" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Auth.ui.setPassword')) ?></button>
</form>
<?= $this->endSection() ?>
