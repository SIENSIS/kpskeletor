<?php
/**
 * The local captcha (setting Auth.captcha): image, reload button and answer field.
 */
?>
<div class="mb-3" data-component="auth/captcha">
    <label for="captcha" class="form-label"><?= esc(lang('Auth.captcha.label')) ?></label>
    <div class="d-flex align-items-center gap-2 mb-2">
        <img src="<?= esc(site_url('auth/captcha')) ?>" alt="<?= esc(lang('Auth.captcha.alt')) ?>" width="150" height="50" class="border rounded auth-captcha-image" data-captcha-image>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-captcha-reload aria-label="<?= esc(lang('Auth.captcha.reload')) ?>" title="<?= esc(lang('Auth.captcha.reload')) ?>"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
    </div>
    <input type="text" inputmode="numeric" class="form-control" id="captcha" name="captcha" required autocomplete="off" maxlength="4">
    <div class="form-text"><?= esc(lang('Auth.captcha.help')) ?></div>
</div>
