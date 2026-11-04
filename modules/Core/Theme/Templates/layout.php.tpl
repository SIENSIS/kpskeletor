<?php
/**
 * Layout "{{Layout}}" of the theme {{Name}} (standalone theme: replace it with your own HTML).
 * Keep the colour-mode attributes and script, the nonces, the import map and core/app.js so
 * dark mode and the core components keep working (see docs/frontend.md).
 *
 * @var string|null $title
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>"<?= theme_mode()->attributes() ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= esc($title ?? lang('Core.app.name')) ?></title>
    <script src="<?= esc(asset('core::js/theme-mode.js')) ?>" <?= csp_script_nonce() ?>></script>
<?php foreach (theme()->styles() as $href): ?>
    <link rel="stylesheet" href="<?= esc($href) ?>">
<?php endforeach ?>
</head>
<body class="layout-{{Layout}}">
<main id="main"><?= $this->renderSection('content') ?></main>
<?php foreach (theme()->scripts() as $src): ?>
    <script src="<?= esc($src) ?>" defer <?= csp_script_nonce() ?>></script>
<?php endforeach ?>
    <script type="application/json" id="kp-config"><?= page_config_json() ?></script>
    <script type="importmap" <?= csp_script_nonce() ?>><?= import_map()->json() ?></script>
    <script type="module" src="<?= esc(asset('core::js/app.js')) ?>" <?= csp_script_nonce() ?>></script>
</body>
</html>
