<?php
/**
 * Login form (auth layout).
 *
 * @var string      $layout
 * @var string|null $email
 * @var bool        $captcha
 * @var bool        $remember
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<form action="<?= esc(site_url('login')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="email" class="form-label"><?= esc(lang('Auth.ui.email')) ?></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= esc($email ?? (string) old('email')) ?>" required autocomplete="username" autofocus maxlength="254">
    </div>
    <div class="mb-3">
        <label for="password" class="form-label"><?= esc(lang('Auth.ui.password')) ?></label>
        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
    </div>
    <?php if ($captcha): ?><?= $this->include('Modules\Auth\Views\_captcha') ?><?php endif ?>
    <?php if ($remember): ?>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
            <label class="form-check-label" for="remember"><?= esc(lang('Auth.ui.rememberMe')) ?></label>
        </div>
    <?php endif ?>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i><?= esc(lang('Auth.ui.login')) ?></button>
</form>
<hr>
<div class="d-flex flex-column gap-1 small text-center">
    <?php if (setting('Auth.allowMagicLinkLogins')): ?>
        <a href="<?= esc(site_url('login/magic-link')) ?>"><?= esc(lang('Auth.ui.useMagicLink')) ?></a>
    <?php endif ?>
    <a href="<?= esc(site_url('reset-password')) ?>"><?= esc(lang('Auth.ui.forgotPassword')) ?></a>
</div>
<?= $this->endSection() ?>
