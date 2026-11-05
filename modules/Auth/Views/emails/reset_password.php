<?php
/**
 * Password-reset email.
 *
 * @var string                            $subject
 * @var string                            $url
 * @var int                               $minutes
 * @var string                            $ipAddress
 * @var string                            $date
 * @var \CodeIgniter\Shield\Entities\User $user
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><?= esc(lang('Auth.mail.hello')) ?></p>
<p><?= esc(lang('Auth.mail.resetBody', [$minutes])) ?></p>
<p><a href="<?= esc($url) ?>"><?= esc(lang('Auth.mail.resetAction')) ?></a></p>
<p><?= esc(lang('Auth.mail.requestInfo', [$date, $ipAddress])) ?></p>
<p><?= esc(lang('Auth.mail.ignore')) ?></p>
</body>
</html>
