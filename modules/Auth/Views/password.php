<?php
/**
 * Password change (back office). Also the screen of a forced password reset.
 *
 * @var string                $layout
 * @var bool                  $forced
 * @var int                   $minLength
 * @var array<string, string> $errors
 */
$invalid  = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row">
    <div class="col-lg-6">
        <?php if ($forced): ?>
            <div class="alert alert-warning" role="status"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Auth.msg.mustChangePassword')) ?></div>
        <?php endif ?>
        <?php ob_start() ?>
        <input type="text" class="d-none" name="username" value="<?= esc((string) auth()->user()?->email) ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
        <div class="mb-3">
            <label for="current_password" class="form-label"><?= esc(lang('Auth.profile.currentPassword')) ?></label>
            <input type="password" class="form-control<?= $invalid('current_password') ?>" id="current_password" name="current_password" required autocomplete="current-password">
            <?= $feedback('current_password') ?>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label"><?= esc(lang('Auth.profile.newPassword')) ?></label>
            <input type="password" class="form-control<?= $invalid('password') ?>" id="password" name="password" required autocomplete="new-password" aria-describedby="password-help">
            <?= $feedback('password') ?>
            <div id="password-help" class="form-text"><?= esc(lang('Auth.profile.passwordHelp', [$minLength])) ?></div>
        </div>
        <div class="mb-3">
            <label for="password_confirm" class="form-label"><?= esc(lang('Auth.profile.confirmPassword')) ?></label>
            <input type="password" class="form-control<?= $invalid('password_confirm') ?>" id="password_confirm" name="password_confirm" required autocomplete="new-password">
            <?= $feedback('password_confirm') ?>
        </div>
        <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Auth.profile.passwordEndsSessions')) ?></p>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => site_url('backend/profile/password'),
            'fields'      => $fields,
            'submitLabel' => lang('Auth.profile.changePassword'),
            'cancelUrl'   => $forced ? null : site_url('backend/profile'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
