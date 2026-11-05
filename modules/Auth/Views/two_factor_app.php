<?php
/**
 * Enrolment of an authenticator app: QR code (always on white), the secret in text and the code
 * that confirms it.
 *
 * @var string $layout
 * @var string $secret Base32 secret
 * @var string $qr     data: URI of the SVG QR code
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?php ob_start() ?>
        <ol class="mb-3">
            <li><?= esc(lang('Auth.twoFactor.appStep1')) ?></li>
            <li><?= esc(lang('Auth.twoFactor.appStep2')) ?></li>
            <li><?= esc(lang('Auth.twoFactor.appStep3')) ?></li>
        </ol>
        <div class="d-flex flex-wrap gap-4 align-items-start mb-3">
            <img src="<?= esc($qr, 'attr') ?>" alt="<?= esc(lang('Auth.twoFactor.qrAlt')) ?>" width="200" height="200" class="bg-white border rounded p-2 qr-code">
            <div>
                <p class="small mb-1"><?= esc(lang('Auth.twoFactor.secretLabel')) ?></p>
                <p class="font-monospace user-select-all text-break mb-0"><?= esc(trim(chunk_split($secret, 4, ' '))) ?></p>
            </div>
        </div>
        <label for="code" class="form-label"><?= esc(lang('Auth.twoFactor.appCode')) ?></label>
        <input type="text" class="form-control font-monospace w-auto" id="code" name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required autofocus>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => site_url('backend/profile/two-factor/app'),
            'fields'      => $fields,
            'title'       => lang('Auth.twoFactor.appTitle'),
            'submitLabel' => lang('Auth.twoFactor.activate'),
            'cancelUrl'   => site_url('backend/profile/two-factor'),
        ]) ?>
    </div>
</div>
<?= $this->endSection() ?>
