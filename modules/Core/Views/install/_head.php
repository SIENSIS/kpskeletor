<?php
/**
 * Head of the web installer's standalone pages (no theme: there is no database yet). Bootstrap
 * from the tracked vendor copy in public/assets/vendor.
 *
 * @var string $base  Path of the site's root ('/' or '/folder/')
 * @var string $title
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title) ?></title>
    <link rel="stylesheet" href="<?= esc($base) ?>assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= esc($base) ?>assets/vendor/bootstrap-icons/font/bootstrap-icons.min.css">
</head>
<body class="bg-body-tertiary">
