<?php
/**
 * The logged-in user's menu in the back-office top bar (the authentication session adds the
 * profile and logout entries).
 */
$user = auth()->user();
?>
<?php if ($user !== null): ?>
<div class="dropdown">
    <button class="btn btn-link text-body text-decoration-none dropdown-toggle d-flex align-items-center gap-2 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?= ui_avatar((string) ($user->username ?? $user->email ?? '?'), null, 'sm') ?>
        <span class="d-none d-md-inline"><?= esc((string) ($user->username ?? $user->email ?? '')) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><span class="dropdown-item-text small text-body-secondary"><?= esc((string) ($user->email ?? '')) ?></span></li>
    </ul>
</div>
<?php endif ?>
