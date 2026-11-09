<?php
/**
 * Impersonation banner (top bar of the back-office layout, added by the `authsession` filter):
 * who is being impersonated, until when, and the way back to the impersonator's account.
 *
 * @var string $username
 * @var int    $expires  Unix time when the impersonation ends
 */
?>
<div class="impersonation-banner alert alert-warning d-flex flex-wrap align-items-center gap-2 py-1 px-2 mb-0 small" role="status" data-impersonation>
    <i class="bi bi-incognito" aria-hidden="true"></i>
    <span><?= esc(lang('Auth.impersonation.banner', [$username])) ?></span>
    <span class="text-body-secondary"><?= esc(lang('Auth.impersonation.until', [\CodeIgniter\I18n\Time::createFromTimestamp($expires)->setTimezone(site_timezone())->format('H:i')])) ?></span>
    <form method="post" action="<?= esc(site_url('auth/impersonation/stop'), 'attr') ?>" class="d-inline">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-warning"><i class="bi bi-box-arrow-left" aria-hidden="true"></i> <?= esc(lang('Auth.impersonation.stop')) ?></button>
    </form>
</div>
