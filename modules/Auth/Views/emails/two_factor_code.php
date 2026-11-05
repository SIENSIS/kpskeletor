<?php
/**
 * 2FA code by email (login step and re-authentication).
 *
 * @var string                            $subject
 * @var string                            $code
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
<p><?= esc(lang('Auth.twoFactor.emailBody', [$minutes])) ?></p>
<p><strong><?= esc($code) ?></strong></p>
<p><?= esc(lang('Auth.mail.requestInfo', [$date, $ipAddress])) ?></p>
<p><?= esc(lang('Auth.twoFactor.emailIgnore')) ?></p>
</body>
</html>
