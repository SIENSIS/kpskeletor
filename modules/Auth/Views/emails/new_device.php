<?php
/**
 * New-device notice.
 *
 * @var string                            $subject
 * @var string                            $device
 * @var string|null                       $ipAddress
 * @var string                            $date
 * @var string                            $url
 * @var \CodeIgniter\Shield\Entities\User $user
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><?= esc(lang('Auth.mail.hello')) ?></p>
<p><?= esc(lang('Auth.mail.newDeviceBody')) ?></p>
<ul>
    <li><?= esc(lang('Auth.mail.device', [$device])) ?></li>
    <li><?= esc(lang('Auth.mail.ip', [(string) $ipAddress])) ?></li>
    <li><?= esc(lang('Auth.mail.date', [$date])) ?></li>
</ul>
<p><?= esc(lang('Auth.mail.newDeviceCheck')) ?></p>
<p><a href="<?= esc($url) ?>"><?= esc(lang('Auth.mail.newDeviceAction')) ?></a></p>
</body>
</html>
