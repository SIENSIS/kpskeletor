<?php
/**
 * Magic-link request form (auth layout).
 *
 * @var string      $layout
 * @var string|null $email
 * @var bool        $captcha
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Auth\Views\_alerts') ?>
<p class="text-body-secondary"><?= esc(lang('Auth.ui.magicLinkIntro')) ?></p>
<form action="<?= esc(site_url('login/magic-link')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <div class="mb-3">
        <label for="email" class="form-label"><?= esc(lang('Auth.ui.email')) ?></label>
        <input type="email" class="form-control" id="email" name="email" value="<?= esc($email ?? '') ?>" required autocomplete="email" autofocus maxlength="254">
    </div>
    <?php if ($captcha): ?><?= $this->include('Modules\Auth\Views\_captcha') ?><?php endif ?>
    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-envelope me-1" aria-hidden="true"></i><?= esc(lang('Auth.ui.sendLink')) ?></button>
</form>
<p class="small text-center mt-3 mb-0"><a href="<?= esc(site_url('login')) ?>"><?= esc(lang('Auth.ui.backToLogin')) ?></a></p>
<?= $this->endSection() ?>
