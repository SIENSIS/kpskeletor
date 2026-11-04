<?php
/**
 * Cookie notice (theme option `cookieNotice`). Remembering the dismissal is added with the
 * front-end scripts.
 */
?>
<?php if (theme_option('cookieNotice') === true): ?>
<div class="cookie-notice alert alert-secondary alert-dismissible fade show shadow mb-0" role="region" aria-label="<?= esc(lang('ThemeDefault.cookies.label')) ?>">
    <i class="bi bi-cookie me-2" aria-hidden="true"></i><?= esc(lang('ThemeDefault.cookies.text')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('ThemeDefault.cookies.accept')) ?>"></button>
</div>
<?php endif ?>
