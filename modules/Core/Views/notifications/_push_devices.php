<?php
/**
 * Push devices of the logged-in user (Profile › Notifications, D-44-02). Without JavaScript the
 * list and its forms work by themselves; `core/push.js` adds "Activate on this device", marks
 * this device and renders the list again after each action.
 *
 * @var array{enabled: bool, devices: list<array<string, mixed>>} $push
 */
$options = [
    'urls' => [
        'state'     => backend_url('profile/push'),
        'subscribe' => backend_url('profile/push/subscribe'),
        'delete'    => backend_url('profile/push/delete') . '/',
        'test'      => backend_url('profile/push/test') . '/',
        'label'     => backend_url('profile/push/label') . '/',
    ],
];
ob_start();
?>
<div data-component="core/push" data-options="<?= esc(json_encode($options, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'attr') ?>">
    <p class="small text-body-secondary"><?= esc(lang('Push.intro')) ?></p>
    <div data-push-status role="status" aria-live="polite" class="alert alert-secondary mb-3"<?= $push['enabled'] ? ' hidden' : '' ?>><?= $push['enabled'] ? '' : esc(lang('Push.off')) ?></div>
    <noscript><div class="alert alert-warning"><?= esc(lang('Push.noScript')) ?></div></noscript>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-primary" data-push-enable hidden><i class="bi bi-bell me-1" aria-hidden="true"></i><?= esc(lang('Push.enable')) ?></button>
        <button type="button" class="btn btn-outline-secondary" data-push-disable hidden><i class="bi bi-bell-slash me-1" aria-hidden="true"></i><?= esc(lang('Push.disable')) ?></button>
    </div>
    <h3 class="h6"><?= esc(lang('Push.devices')) ?></h3>
    <p class="small text-body-secondary mb-2" data-push-empty<?= $push['devices'] === [] ? '' : ' hidden' ?>><?= esc(lang('Push.noDevices')) ?></p>
    <ul class="list-group" data-push-devices>
        <?php foreach ($push['devices'] as $device): ?>
            <li class="list-group-item d-flex flex-wrap align-items-center gap-2" data-device-id="<?= (int) $device['id'] ?>">
                <div class="me-auto text-break">
                    <div class="fw-semibold"><?= esc($device['label']) ?></div>
                    <div class="small text-body-secondary"><?= esc(lang('Push.added', [\Modules\Core\Push\DeviceLabel::date($device['created_at'])])) ?></div>
                </div>
                <form action="<?= esc(backend_url('profile/push/test/' . (int) $device['id'])) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-send me-1" aria-hidden="true"></i><?= esc(lang('Push.test.button')) ?></button>
                </form>
                <form action="<?= esc(backend_url('profile/push/delete/' . (int) $device['id'])) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Push.removeConfirm'), 'attr') ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Push.remove')) ?></button>
                </form>
            </li>
        <?php endforeach ?>
    </ul>
</div>
<?php $body = (string) ob_get_clean() ?>
<div class="mb-4"><?= theme_component('card', ['title' => lang('Push.title'), 'icon' => 'bi bi-phone-vibrate', 'body' => $body]) ?></div>
