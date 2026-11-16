<?php
/**
 * Maintenance mode page (503, Modules\Core\Filters\Maintenance). Plain HTML on purpose: while the
 * mode is on the database may be replaced (a restore), so nothing here reads settings, the theme
 * or the database. `$message` is the administrator's plain-text message (MaintenanceMode).
 *
 * @var string $message
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= esc(lang('Core.maintenance.title')) ?></title>
</head>
<body>
<main>
    <h1><?= esc(lang('Core.maintenance.title')) ?></h1>
    <p><?= esc(($message ?? '') !== '' ? $message : lang('Core.maintenance.message')) ?></p>
</main>
</body>
</html>
