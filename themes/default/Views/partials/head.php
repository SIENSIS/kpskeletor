<?php
/**
 * <head> content shared by every layout: charset, viewport, title, SEO meta (public layout),
 * the theme's stylesheets (root theme first) and the CSS variables set by the theme options.
 *
 * @var string|null                $title
 * @var array<string, mixed>|null  $meta description, canonical, robots, og (bool), image, type
 */
$siteName  = lang('Core.app.name');
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $siteName : $siteName;
$meta ??= [];
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
<?php foreach (theme()->styles() as $href): ?>
    <link rel="stylesheet" href="<?= esc($href) ?>">
<?php endforeach ?>
<?php $variables = theme()->options()->css(); ?>
<?php if ($variables !== ''): ?>
    <style <?= csp_style_nonce() ?>><?= $variables /* validated hex colours only (ThemeOptions) */ ?></style>
<?php endif ?>
