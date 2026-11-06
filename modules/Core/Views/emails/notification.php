<?php
/**
 * Default email of a notification (the `email` channel), rendered in the recipient's language.
 *
 * @var string                                          $subject
 * @var \Modules\Core\Notifications\NotificationMessage $message
 * @var string|null                                     $url
 * @var string                                          $preferencesUrl
 */
?>
<!doctype html>
<html lang="<?= esc(service('language')->getLocale()) ?>">
<head><meta charset="utf-8"><title><?= esc($subject) ?></title></head>
<body>
<p><strong><?= esc($message->title) ?></strong></p>
<?php if ($message->body !== ''): ?>
<p><?= nl2br(esc($message->body)) ?></p>
<?php endif ?>
<?php if ($url !== null): ?>
<p><a href="<?= esc($url) ?>"><?= esc(lang('Core.notifications.open')) ?></a></p>
<?php endif ?>
<hr>
<p><small><?= esc(lang('Core.notifications.emailFooter')) ?> <a href="<?= esc($preferencesUrl) ?>"><?= esc(lang('Core.notifications.preferences')) ?></a></small></p>
</body>
</html>
