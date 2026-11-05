<?php
/**
 * 2FA step of a login (auth layout): the code of the user's method, or a recovery code.
 *
 * @var string                                 $layout
 * @var \CodeIgniter\Shield\Entities\User|null $user
 * @var string|null                            $method   email | totp
 * @var string                                 $email    masked address
 * @var string|null                            $error
 * @var bool|null                              $recovery the last attempt used a recovery code
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<?php if (isset($user)): ?>
    <p class="text-body-secondary">
        <?php if ($method === 'totp'): ?>
            <i class="bi bi-phone me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.enterAppCode')) ?>
        <?php else: ?>
            <i class="bi bi-envelope me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.enterEmailCode', [$email])) ?>
        <?php endif ?>
    </p>
    <form action="<?= esc(site_url('auth/a/verify')) ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
            <label for="code" class="form-label"><?= esc(lang('Auth.twoFactor.code')) ?></label>
            <input type="text" class="form-control form-control-lg text-center font-monospace" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus>
        </div>
        <details class="mb-3"<?= ! empty($recovery) ? ' open' : '' ?>>
            <summary class="small"><?= esc(lang('Auth.twoFactor.useRecoveryCode')) ?></summary>
            <label for="recovery_code" class="form-label mt-2"><?= esc(lang('Auth.twoFactor.recoveryCode')) ?></label>
            <input type="text" class="form-control font-monospace" id="recovery_code" name="recovery_code" maxlength="20" autocomplete="off" aria-describedby="recovery-help">
            <div id="recovery-help" class="form-text"><?= esc(lang('Auth.twoFactor.recoveryHelp')) ?></div>
        </details>
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.verify')) ?></button>
    </form>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <?php if ($method === 'email'): ?>
            <form action="<?= esc(site_url('auth/a/handle')) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-link btn-sm px-0"><?= esc(lang('Auth.twoFactor.sendNewCode')) ?></button>
            </form>
        <?php else: ?>
            <span></span>
        <?php endif ?>
        <form action="<?= esc(site_url('auth/a/cancel')) ?>" method="post">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link btn-sm px-0"><?= esc(lang('Auth.twoFactor.cancel')) ?></button>
        </form>
    </div>
<?php else: ?>
    <a class="btn btn-primary w-100" href="<?= esc(site_url('login')) ?>"><?= esc(lang('Auth.ui.login')) ?></a>
<?php endif ?>
<?= $this->endSection() ?>
