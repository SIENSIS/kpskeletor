<?php
/**
 * Re-authentication (auth layout): the password and, with 2FA, a code or a recovery code.
 *
 * @var string                            $layout
 * @var \CodeIgniter\Shield\Entities\User $user
 * @var string|null                       $method email | totp | null
 * @var string|null                       $error
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<p class="text-body-secondary"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= esc(lang('Auth.reauth.intro')) ?></p>
<form action="<?= esc(site_url('auth/reauth')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="text" class="d-none" name="username" value="<?= esc((string) $user->email) ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
    <div class="mb-3">
        <label for="password" class="form-label"><?= esc(lang('Auth.ui.password')) ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" autofocus>
    </div>
    <?php if ($method !== null): ?>
        <div class="mb-3">
            <label for="code" class="form-label"><?= esc(lang($method === 'totp' ? 'Auth.twoFactor.appCode' : 'Auth.twoFactor.emailCode')) ?></label>
            <input type="text" class="form-control font-monospace" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code">
        </div>
        <details class="mb-3">
            <summary class="small"><?= esc(lang('Auth.twoFactor.useRecoveryCode')) ?></summary>
            <label for="recovery_code" class="form-label mt-2"><?= esc(lang('Auth.twoFactor.recoveryCode')) ?></label>
            <input type="text" class="form-control font-monospace" id="recovery_code" name="recovery_code" maxlength="20" autocomplete="off">
        </details>
    <?php endif ?>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= esc(lang('Auth.reauth.confirm')) ?></button>
</form>
<?php if ($method === 'email'): ?>
    <form action="<?= esc(site_url('auth/reauth/send-code')) ?>" method="post" class="text-center mt-3">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-link btn-sm"><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= esc(lang('Auth.reauth.sendCode')) ?></button>
    </form>
<?php endif ?>
<div class="text-center mt-2">
    <a class="small" href="<?= esc(site_url('backend/profile')) ?>"><?= esc(lang('Auth.reauth.back')) ?></a>
</div>
<?= $this->endSection() ?>
