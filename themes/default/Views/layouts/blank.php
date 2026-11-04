<?php
/**
 * Blank layout: the theme's styles and scripts around the `content` section, nothing else
 * (error pages, embeds, pop-ups).
 *
 * @var string|null $title
 */
$font = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<?= $this->include(theme_resolve('partials/head')) ?>
</head>
<body class="layout-blank theme-font-<?= esc($font) ?>">
<?= $this->renderSection('content') ?>
<?= $this->include(theme_resolve('partials/scripts')) ?>
</body>
</html>
