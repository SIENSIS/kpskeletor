<?php
/**
 * Idle lock screen (auth layout): the password unlocks the session.
 *
 * @var string                            $layout
 * @var \CodeIgniter\Shield\Entities\User $user
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<div class="text-center mb-3">
    <?= ui_avatar((string) ($user->username ?? $user->email ?? '?'), null, 'lg') ?>
    <p class="mt-2 mb-0 fw-semibold"><?= esc((string) $user->email) ?></p>
    <p class="small text-body-secondary"><?= esc(lang('Auth.lock.intro')) ?></p>
</div>
<form action="<?= esc(site_url('auth/lock')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="text" class="d-none" name="username" value="<?= esc((string) $user->email) ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
    <div class="mb-3">
        <label for="password" class="form-label"><?= esc(lang('Auth.ui.password')) ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" autofocus>
    </div>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-unlock me-1" aria-hidden="true"></i><?= esc(lang('Auth.lock.unlock')) ?></button>
</form>
<form action="<?= esc(site_url('logout')) ?>" method="post" class="text-center mt-3">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-link btn-sm"><?= esc(lang('Auth.lock.notYou')) ?></button>
</form>
<?= $this->endSection() ?>
