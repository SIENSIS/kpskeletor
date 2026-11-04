<?php
/**
 * Site logo (theme options `logo` and `logoDark` for dark backgrounds) or icon and site name.
 *
 * @var string|null $brandUrl
 * @var string|null $brandClass
 */
$siteName = lang('Core.app.name');
$toUrl    = static fn (string $logo): string => $logo === '' ? '' : (preg_match('#^https?://#i', $logo) === 1 ? $logo : base_url(ltrim($logo, '/')));
$logoUrl  = $toUrl((string) theme_option('logo'));
$darkUrl  = $toUrl((string) theme_option('logoDark'));
?>
<a class="brand d-inline-flex align-items-center gap-2 text-decoration-none <?= esc($brandClass ?? '') ?>" href="<?= esc(ui_safe_url($brandUrl ?? base_url('/'))) ?>">
    <?php if ($logoUrl !== ''): ?>
        <img src="<?= esc(ui_safe_url($logoUrl)) ?>" alt="<?= esc($siteName) ?>" class="brand-logo<?= $darkUrl !== '' ? ' brand-logo-light' : '' ?>">
        <?php if ($darkUrl !== ''): ?><img src="<?= esc(ui_safe_url($darkUrl)) ?>" alt="<?= esc($siteName) ?>" class="brand-logo brand-logo-dark"><?php endif ?>
    <?php else: ?>
        <i class="bi bi-grid-3x3-gap-fill brand-icon" aria-hidden="true"></i>
        <span class="brand-name fw-semibold"><?= esc($siteName) ?></span>
    <?php endif ?>
</a>
