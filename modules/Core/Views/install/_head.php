<?php
/**
 * Head of the web installer's standalone pages (no theme: there is no database yet). Bootstrap
 * from the tracked vendor copy in public/assets/vendor; the colour mode follows the system
 * (`core/theme-mode.js`) when the Core's scripts are published.
 *
 * @var string    $base    Path of the site's root ('/' or '/folder/')
 * @var string    $title
 * @var bool|null $scripts Whether the Core's scripts are published (`_scripts`)
 * @var int|null  $refresh Reload the page after these seconds (pages without JavaScript)
 */
$scripts ??= false;
$refresh ??= null;
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php if ($refresh !== null) : ?>
        <meta http-equiv="refresh" content="<?= (int) $refresh ?>">
    <?php endif ?>
    <title><?= esc($title) ?></title>
    <?php if ($scripts) : ?>
        <script src="<?= esc(asset('core::js/theme-mode.js')) ?>" <?= csp_script_nonce() ?>></script>
    <?php endif ?>
    <link rel="stylesheet" href="<?= esc($base) ?>assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= esc($base) ?>assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
</head>
<body class="bg-body-tertiary">
