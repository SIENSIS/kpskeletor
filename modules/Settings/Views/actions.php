<?php
/**
 * Settings › Actions: test email, clear caches, publish assets, maintenance mode.
 *
 * @var string                                                                            $layout
 * @var list<array{id: string, label: string, url: string, icon: string, active: bool}>  $tabs
 * @var string                                                                            $email
 * @var int                                                                               $pending
 * @var bool                                                                              $canPublish
 * @var array{reason: string, since: string, allow: list<string>, message: string, bypass: bool}|null $maintenance
 * @var int                                                                               $messageMax
 */
$actions = [
    ['url' => 'backend/settings/actions/email', 'icon' => 'bi bi-envelope', 'key' => 'email', 'args' => [$email, $pending], 'enabled' => $email !== '', 'confirm' => null],
    ['url' => 'backend/settings/actions/caches', 'icon' => 'bi bi-arrow-repeat', 'key' => 'caches', 'args' => [], 'enabled' => true, 'confirm' => null],
    ['url' => 'backend/settings/actions/assets', 'icon' => 'bi bi-box-arrow-up', 'key' => 'assets', 'args' => [], 'enabled' => $canPublish, 'confirm' => lang('Settings.actions.assetsConfirm')],
];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Settings\Views\_tabs') ?>
<div class="row g-3">
    <?php foreach ($actions as $action): ?>
        <div class="col-md-6 col-xl-4">
            <?php ob_start() ?>
            <p class="small text-body-secondary"><?= esc(lang('Settings.actions.' . $action['key'] . 'Help', $action['args'])) ?></p>
            <?php if ($action['enabled']): ?>
                <form action="<?= esc(site_url($action['url']), 'attr') ?>" method="post"<?= $action['confirm'] !== null ? ' data-confirm="' . esc($action['confirm'], 'attr') . '"' : '' ?>>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-primary"><i class="<?= esc($action['icon'], 'attr') ?> me-1" aria-hidden="true"></i><?= esc(lang('Settings.actions.' . $action['key'])) ?></button>
                </form>
            <?php else: ?>
                <p class="small mb-0"><?= ui_badge(lang('Settings.actions.unavailable'), 'secondary') ?></p>
            <?php endif ?>
            <?php $body = (string) ob_get_clean() ?>
            <?= theme_component('card', ['title' => lang('Settings.actions.' . $action['key']), 'icon' => $action['icon'], 'body' => $body]) ?>
        </div>
    <?php endforeach ?>
    <div class="col-md-6 col-xl-4">
        <?php ob_start() ?>
        <p class="small text-body-secondary"><?= esc(lang('Settings.actions.maintenanceHelp')) ?></p>
        <p class="small" data-maintenance-state>
            <?php if ($maintenance === null): ?>
                <?= ui_badge(lang('Settings.actions.maintenanceIsOff'), 'success') ?>
            <?php else: ?>
                <?= ui_badge(lang('Settings.actions.maintenanceIsOn'), 'danger') ?>
                <span class="text-body-secondary"><?= esc(lang('Settings.actions.maintenanceSince')) ?> <?= ui_date($maintenance['since'] . ' UTC', 'datetime') ?> · <?= esc(lang('Settings.actions.maintenanceReason', [$maintenance['reason']])) ?></span>
            <?php endif ?>
        </p>
        <?php if (! $canPublish): ?>
            <p class="small mb-0"><?= ui_badge(lang('Settings.actions.unavailable'), 'secondary') ?></p>
        <?php elseif ($maintenance === null): ?>
            <form action="<?= esc(site_url('backend/settings/actions/maintenance'), 'attr') ?>" method="post" data-confirm="<?= esc(lang('Settings.actions.maintenanceConfirm'), 'attr') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="state" value="on">
                <div class="mb-2">
                    <label for="maintenance-message" class="form-label small"><?= esc(lang('Settings.actions.maintenanceMessage')) ?></label>
                    <textarea id="maintenance-message" name="message" class="form-control form-control-sm" rows="2" maxlength="<?= (int) $messageMax ?>"></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-cone-striped me-1" aria-hidden="true"></i><?= esc(lang('Settings.actions.maintenanceOn')) ?></button>
            </form>
        <?php else: ?>
            <?php if ($maintenance['message'] !== ''): ?>
                <p class="small fst-italic"><?= esc($maintenance['message']) ?></p>
            <?php endif ?>
            <form action="<?= esc(site_url('backend/settings/actions/maintenance'), 'attr') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="state" value="off">
                <button type="submit" class="btn btn-primary"<?= $maintenance['reason'] !== \Modules\Core\Maintenance\MaintenanceMode::REASON_MANUAL ? ' disabled' : '' ?>><i class="bi bi-check-circle me-1" aria-hidden="true"></i><?= esc(lang('Settings.actions.maintenanceOff')) ?></button>
            </form>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Settings.actions.maintenance'), 'icon' => 'bi bi-cone-striped', 'body' => $body]) ?>
    </div>
</div>
<?= $this->endSection() ?>
