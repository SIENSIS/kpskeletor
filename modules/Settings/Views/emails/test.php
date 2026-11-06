<?php
/**
 * The test email of Settings › Actions.
 *
 * @var string $subject
 * @var string $site
 */
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale()) ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><strong><?= esc($subject) ?></strong></p>
<p><?= esc(lang('Settings.actions.emailBody', [$site])) ?></p>
</body>
</html>
