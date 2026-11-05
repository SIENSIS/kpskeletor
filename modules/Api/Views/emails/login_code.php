<?php
/**
 * Sign-in code of an app login (users with email 2FA and users without 2FA).
 *
 * @var string                            $subject
 * @var string                            $code
 * @var int                               $minutes
 * @var string                            $device
 * @var string                            $ipAddress
 * @var string                            $date
 * @var string                            $denyUrl
 * @var \CodeIgniter\Shield\Entities\User $user
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><?= esc(lang('Auth.mail.hello')) ?></p>
<p><?= esc(lang('Api.login.emailBody', [$minutes])) ?></p>
<p><strong><?= esc($code) ?></strong></p>
<ul>
    <li><?= esc(lang('Auth.mail.device', [$device])) ?></li>
    <li><?= esc(lang('Auth.mail.ip', [$ipAddress])) ?></li>
    <li><?= esc(lang('Auth.mail.date', [$date])) ?></li>
</ul>
<p><?= esc(lang('Api.login.emailNotYou')) ?></p>
<p><a href="<?= esc($denyUrl) ?>"><?= esc(lang('Api.login.denyAction')) ?></a></p>
</body>
</html>
