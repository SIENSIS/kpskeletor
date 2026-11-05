<?php
/**
 * Deny an app sign-in (link of the sign-in code email).
 *
 * @var string                    $layout
 * @var string                    $id
 * @var array<string, mixed>|null $challenge Pending challenge, or null
 * @var bool                      $denied
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if ($denied): ?>
    <p class="text-center"><i class="bi bi-shield-check display-6 text-success" aria-hidden="true"></i></p>
    <p><?= esc(lang('Api.deny.done')) ?></p>
    <p class="small"><?= esc(lang('Api.deny.changePassword')) ?></p>
    <p class="small text-center mb-0"><a href="<?= esc(site_url('reset-password')) ?>"><?= esc(lang('Api.deny.resetLink')) ?></a></p>
<?php elseif ($challenge === null): ?>
    <p><?= esc(lang('Api.deny.notPending')) ?></p>
    <p class="small"><?= esc(lang('Api.deny.changePassword')) ?></p>
    <p class="small text-center mb-0"><a href="<?= esc(site_url('login')) ?>"><?= esc(lang('Auth.ui.backToLogin')) ?></a></p>
<?php else: ?>
    <p><?= esc(lang('Api.deny.intro')) ?></p>
    <ul>
        <li><?= esc(lang('Auth.mail.device', [(string) $challenge['device']])) ?></li>
        <li><?= esc(lang('Auth.mail.ip', [(string) $challenge['ip_address']])) ?></li>
        <li><?= esc(lang('Auth.mail.date', [(string) $challenge['created_at']])) ?></li>
    </ul>
    <form action="<?= esc(site_url('auth/api-login/' . $id)) ?>" method="post">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i><?= esc(lang('Api.deny.confirm')) ?></button>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
