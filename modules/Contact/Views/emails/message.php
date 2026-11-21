<?php
/**
 * Email to a recipient's address (queued; from the site's address, the visitor in `Reply-To`).
 * Every value typed by the visitor is escaped; the message keeps its line breaks.
 *
 * @var string                                                                  $subject
 * @var array{name: string, email: string, subject: string, message: string} $data
 * @var string                                                                  $recipient
 * @var string                                                                  $site
 * @var string                                                                  $url
 */
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale(), 'attr') ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><?= esc(lang('Contact.email.intro', [$site, $recipient])) ?></p>
<table role="presentation" cellpadding="4">
    <tr><th align="left"><?= esc(lang('Contact.fields.name')) ?></th><td><?= esc($data['name']) ?></td></tr>
    <tr><th align="left"><?= esc(lang('Contact.fields.email')) ?></th><td><?= esc($data['email']) ?></td></tr>
    <tr><th align="left"><?= esc(lang('Contact.fields.subject')) ?></th><td><?= esc($data['subject']) ?></td></tr>
</table>
<p><?= nl2br(esc($data['message'])) ?></p>
<p><?= esc(lang('Contact.email.reply')) ?></p>
<p><a href="<?= esc($url, 'attr') ?>"><?= esc(lang('Contact.email.open')) ?></a></p>
</body>
</html>
