<?php
/**
 * Cookie notice (theme option `cookieNotice`). Closing it is remembered in the kp_cookies cookie
 * (core/ui/cookie-notice.js), so it is not printed again.
 */
$request   = service('request');
$dismissed = $request instanceof CodeIgniter\HTTP\IncomingRequest && $request->getCookie('kp_cookies') === '1';
?>
<?php if (theme_option('cookieNotice') === true && ! $dismissed): ?>
<div class="cookie-notice alert alert-secondary alert-dismissible fade show shadow mb-0" role="region" aria-label="<?= esc(lang('ThemeDefault.cookies.label')) ?>" data-cookie-notice>
    <i class="bi bi-cookie me-2" aria-hidden="true"></i><?= esc(lang('ThemeDefault.cookies.text')) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('ThemeDefault.cookies.accept')) ?>"></button>
</div>
<?php endif ?>
