<?php
/**
 * The logged-in user's menu in the back-office top bar: the `user` zone of the generated menu
 * (profile, "my sessions"… filtered by permission) and logout (a POST form with the CSRF token).
 */
$user    = auth()->user();
$entries = $user !== null ? backend_menu('user') : [];
?>
<?php if ($user !== null): ?>
<div class="dropdown">
    <button class="btn btn-link text-body text-decoration-none dropdown-toggle d-flex align-items-center gap-2 px-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <?= ui_avatar((string) ($user->username ?? $user->email ?? '?'), null, 'sm') ?>
        <span class="d-none d-md-inline"><?= esc((string) ($user->username ?? $user->email ?? '')) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li><span class="dropdown-item-text small text-body-secondary"><?= esc((string) ($user->email ?? '')) ?></span></li>
        <li><hr class="dropdown-divider"></li>
        <?php foreach ($entries as $entry): ?>
            <?php foreach ([$entry, ...$entry->children] as $item): ?>
                <?php if ($item->url() !== null): ?>
                    <li><a class="dropdown-item<?= $item->active ? ' active' : '' ?>" href="<?= esc($item->url()) ?>"<?= $item->active ? ' aria-current="page"' : '' ?>><i class="<?= esc($item->icon) ?> me-2" aria-hidden="true"></i><?= esc($item->text()) ?></a></li>
                <?php endif ?>
            <?php endforeach ?>
        <?php endforeach ?>
        <?php if ($entries !== []): ?>
            <li><hr class="dropdown-divider"></li>
        <?php endif ?>
        <li>
            <form action="<?= esc(site_url('logout')) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i><?= esc(lang('Core.user.logout')) ?></button>
            </form>
        </li>
    </ul>
</div>
<?php endif ?>
