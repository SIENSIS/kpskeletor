<?php
/**
 * Notifications bell of the back-office top bar (`core/ui/bell`): unread count, the latest
 * notifications (loaded when opened), "mark all as read" and a link to the full list. Shown to
 * users holding `core-notifications.list`; works without JavaScript through the links and form.
 */
if (auth()->user() === null || ! can('core-notifications.list')) {
    return;
}

$unread = (new \Modules\Core\Notifications\Inbox((int) auth()->id()))->unreadCount();
$label  = lang('Core.notifications.bell', [$unread]);
$config = json_encode([
    'url'     => site_url('backend/notifications/bell'),
    'readAll' => site_url('backend/notifications/read-all'),
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="dropdown notification-bell" data-component="core/ui/bell" data-options="<?= esc((string) $config, 'attr') ?>">
    <button class="btn btn-link text-body position-relative px-2" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-display="static" aria-expanded="false" aria-label="<?= esc($label) ?>" title="<?= esc(lang('Core.notifications.title')) ?>" data-bell-button>
        <i class="bi bi-bell" aria-hidden="true"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger bell-count" data-bell-count<?= $unread === 0 ? ' hidden' : '' ?>><?= $unread > 99 ? '99+' : esc((string) $unread) ?></span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-0 bell-menu">
        <div class="d-flex align-items-center justify-content-between gap-2 px-3 py-2 border-bottom">
            <strong class="small"><?= esc(lang('Core.notifications.title')) ?></strong>
            <form action="<?= esc(site_url('backend/notifications/read-all')) ?>" method="post" class="m-0" data-bell-read-all>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-link btn-sm p-0"><?= esc(lang('Core.notifications.markAllRead')) ?></button>
            </form>
        </div>
        <div class="list-group list-group-flush overflow-y-auto bell-list" data-bell-list>
            <p class="small text-body-secondary px-3 py-3 mb-0" data-bell-placeholder><?= esc(lang('Core.notifications.loading')) ?></p>
        </div>
        <div class="d-flex border-top">
            <a class="dropdown-item text-center small py-2" href="<?= esc(site_url('backend/notifications')) ?>"><?= esc(lang('Core.notifications.seeAll')) ?></a>
            <a class="dropdown-item text-center small py-2 border-start" href="<?= esc(site_url('backend/profile/notifications')) ?>"><?= esc(lang('Core.notifications.preferencesShort')) ?></a>
        </div>
    </div>
</div>
