<?php
/**
 * <head> content shared by every layout: charset, viewport, title, SEO meta (public layout), the
 * colour-mode script (before the stylesheets: no flash of the wrong colours), the theme's
 * stylesheets (root theme first), the PWA links, the CSRF token for scripts (only when the page
 * already has a session) and the CSS variables set by the theme options.
 *
 * @var string|null                $title
 * @var array<string, mixed>|null  $meta description, canonical, robots, og (bool), image, type
 */
$siteName  = site_name();
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $siteName : $siteName;
$meta ??= [];
$pwa = pwa_enabled();
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= esc($pageTitle) ?></title>
<?php if (! empty($meta['description'])): ?>
    <meta name="description" content="<?= esc($meta['description']) ?>">
<?php endif ?>
<?php if (! empty($meta['robots'])): ?>
    <meta name="robots" content="<?= esc($meta['robots']) ?>">
<?php endif ?>
<?php if (! empty($meta['canonical'])): ?>
    <link rel="canonical" href="<?= esc(ui_safe_url($meta['canonical'])) ?>">
<?php endif ?>
<?php if (! empty($meta['og'])): ?>
    <meta property="og:site_name" content="<?= esc($siteName) ?>">
    <meta property="og:title" content="<?= esc($title ?? $siteName) ?>">
    <meta property="og:type" content="<?= esc($meta['type'] ?? 'website') ?>">
<?php if (! empty($meta['description'])): ?>
    <meta property="og:description" content="<?= esc($meta['description']) ?>">
<?php endif ?>
<?php if (! empty($meta['image'])): ?>
    <meta property="og:image" content="<?= esc(ui_safe_url($meta['image'])) ?>">
<?php endif ?>
<?php endif ?>
    <script src="<?= esc(asset('core::js/theme-mode.js')) ?>" <?= csp_script_nonce() ?>></script>
<?php foreach (theme()->styles() as $href): ?>
    <link rel="stylesheet" href="<?= esc($href) ?>">
<?php endforeach ?>
<?php if ($pwa): ?>
    <link rel="manifest" href="<?= esc(base_url('manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= esc(pwa_icon_url(192)) ?>">
<?php foreach (pwa_theme_colors() as $scheme => $color): ?>
    <meta name="theme-color" media="(prefers-color-scheme: <?= esc($scheme) ?>)" content="<?= esc($color) ?>">
<?php endforeach ?>
<?php endif ?>
<?php if (session_status() === PHP_SESSION_ACTIVE): ?>
    <meta name="csrf-header" content="<?= esc(csrf_header()) ?>">
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
<?php endif ?>
<?php $variables = theme()->options()->css(); ?>
<?php if ($variables !== ''): ?>
    <style <?= csp_style_nonce() ?>><?= $variables /* validated hex colours only (ThemeOptions) */ ?></style>
<?php endif ?>
