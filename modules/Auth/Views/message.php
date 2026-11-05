<?php
/**
 * A message on the auth layout (e.g. "check your email").
 *
 * @var string $layout
 * @var string $message
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-center"><i class="bi bi-envelope-check display-6 text-primary" aria-hidden="true"></i></p>
<p class="text-center"><?= esc($message) ?></p>
<p class="small text-center mb-0"><a href="<?= esc(site_url('login')) ?>"><?= esc(lang('Auth.ui.backToLogin')) ?></a></p>
<?= $this->endSection() ?>
