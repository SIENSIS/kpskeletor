<?php
/**
 * The user's two-factor authentication (back office).
 *
 * @var string            $layout
 * @var string|null       $method        email | totp | null
 * @var int               $codesLeft     unused recovery codes
 * @var list<string>|null $newCodes      recovery codes generated just now (shown once)
 * @var bool              $required      a group of the user requires 2FA
 * @var bool              $totpAvailable an encryption key is configured
 * @var bool              $canEdit
 */
$methods = [
    'email' => ['icon' => 'bi bi-envelope', 'title' => lang('Auth.twoFactor.methodEmail'), 'help' => lang('Auth.twoFactor.methodEmailHelp')],
    'totp'  => ['icon' => 'bi bi-phone', 'title' => lang('Auth.twoFactor.methodApp'), 'help' => lang('Auth.twoFactor.methodAppHelp')],
];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if (is_array($newCodes) && $newCodes !== []): ?>
    <?php ob_start() ?>
    <p><?= esc(lang('Auth.twoFactor.codesIntro')) ?></p>
    <ul class="list-unstyled row row-cols-2 g-2 font-monospace fs-5 mb-3">
        <?php foreach ($newCodes as $code): ?><li class="col"><?= esc($code) ?></li><?php endforeach ?>
    </ul>
    <p class="small text-body-secondary mb-0"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.codesOnce')) ?></p>
    <?php $codesBody = (string) ob_get_clean() ?>
    <div class="mb-4"><?= theme_component('card', ['title' => lang('Auth.twoFactor.recoveryCodes'), 'icon' => 'bi bi-key', 'body' => $codesBody]) ?></div>
<?php endif ?>
<?php if ($required && $method === null): ?>
    <div class="alert alert-warning" role="status"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.required')) ?></div>
<?php endif ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?php ob_start() ?>
        <p class="mb-3">
            <?= esc(lang('Auth.twoFactor.status')) ?>
            <?= $method === null ? ui_badge(lang('Auth.twoFactor.off'), 'secondary') : ui_badge($methods[$method]['title'], 'success') ?>
        </p>
        <div class="list-group mb-3">
            <?php foreach ($methods as $key => $info): ?>
                <div class="list-group-item d-flex flex-wrap gap-3 align-items-center justify-content-between">
                    <div class="me-auto">
                        <div class="fw-semibold"><i class="<?= esc($info['icon']) ?> me-2" aria-hidden="true"></i><?= esc($info['title']) ?></div>
                        <div class="small text-body-secondary"><?= esc($info['help']) ?></div>
                    </div>
                    <?php if ($method === $key): ?>
                        <?= ui_badge(lang('Auth.twoFactor.active'), 'success') ?>
                    <?php elseif ($canEdit && $key === 'email'): ?>
                        <form action="<?= esc(site_url('backend/profile/two-factor/email')) ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline-primary btn-sm"><?= esc(lang('Auth.twoFactor.use')) ?></button>
                        </form>
                    <?php elseif ($canEdit && $totpAvailable): ?>
                        <a class="btn btn-outline-primary btn-sm" href="<?= esc(site_url('backend/profile/two-factor/app')) ?>"><?= esc(lang('Auth.twoFactor.use')) ?></a>
                    <?php elseif ($key === 'totp' && ! $totpAvailable): ?>
                        <span class="small text-body-secondary"><?= esc(lang('Auth.twoFactor.noEncryptionKey')) ?></span>
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        <?php if ($method !== null && $canEdit): ?>
            <?php if ($required): ?>
                <p class="small text-body-secondary mb-0"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.requiredByGroup')) ?></p>
            <?php else: ?>
                <form action="<?= esc(site_url('backend/profile/two-factor/disable')) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="<?= esc(lang('Auth.twoFactor.confirmDisable')) ?>"><i class="bi bi-shield-x me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.disable')) ?></button>
                </form>
            <?php endif ?>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Auth.twoFactor.methodTitle'), 'icon' => 'bi bi-shield-check', 'body' => $body]) ?>
    </div>
    <div class="col-lg-4">
        <?php ob_start() ?>
        <?php if ($method === null): ?>
            <p class="small text-body-secondary mb-0"><?= esc(lang('Auth.twoFactor.codesAfterEnable')) ?></p>
        <?php else: ?>
            <p><?= esc(lang('Auth.twoFactor.codesLeft', [$codesLeft])) ?></p>
            <?php if ($canEdit): ?>
                <form action="<?= esc(site_url('backend/profile/two-factor/recovery-codes')) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm" data-confirm="<?= esc(lang('Auth.twoFactor.confirmRegenerate')) ?>"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.regenerate')) ?></button>
                </form>
            <?php endif ?>
        <?php endif ?>
        <?php $side = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Auth.twoFactor.recoveryCodes'), 'icon' => 'bi bi-key', 'body' => $side]) ?>
        <p class="small text-body-secondary mt-3"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.reauthNote')) ?></p>
    </div>
</div>
<?= $this->endSection() ?>
