<?php
/**
 * Settings › Actions: test email, clear caches, publish assets.
 *
 * @var string                                                                            $layout
 * @var list<array{id: string, label: string, url: string, icon: string, active: bool}>  $tabs
 * @var string                                                                            $email
 * @var int                                                                               $pending
 * @var bool                                                                              $canPublish
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
</div>
<?= $this->endSection() ?>
