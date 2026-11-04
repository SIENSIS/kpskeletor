<?php
/**
 * Site logo (theme option `logo`) or icon and site name.
 *
 * @var string|null $brandUrl
 * @var string|null $brandClass
 */
$logo     = (string) theme_option('logo');
$siteName = lang('Core.app.name');
$logoUrl  = $logo === '' ? '' : (preg_match('#^https?://#i', $logo) === 1 ? $logo : base_url(ltrim($logo, '/')));
?>
<a class="brand d-inline-flex align-items-center gap-2 text-decoration-none <?= esc($brandClass ?? '') ?>" href="<?= esc(ui_safe_url($brandUrl ?? base_url('/'))) ?>">
    <?php if ($logoUrl !== ''): ?>
        <img src="<?= esc(ui_safe_url($logoUrl)) ?>" alt="<?= esc($siteName) ?>" class="brand-logo">
    <?php else: ?>
        <i class="bi bi-grid-3x3-gap-fill brand-icon" aria-hidden="true"></i>
        <span class="brand-name fw-semibold"><?= esc($siteName) ?></span>
    <?php endif ?>
</a>
