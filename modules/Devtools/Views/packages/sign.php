<?php
/**
 * `backend/devtools/packages/sign`: the local signing form (D-43-01). The key and the password
 * are never echoed back, never kept: no old input, autocomplete off, no spell checking.
 *
 * @var string $layout
 * @var string $file
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<div class="row">
    <div class="col-12 col-lg-8 col-xxl-6">
        <div class="alert alert-info small" role="note" data-sign-warning>
            <i class="bi bi-info-circle" aria-hidden="true"></i> <?= esc(lang('Devtools.packages.sign.warning')) ?>
        </div>
        <form method="post" action="<?= esc(backend_url('devtools/packages/sign'), 'attr') ?>" enctype="multipart/form-data" autocomplete="off" class="card">
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="package" value="<?= esc($file, 'attr') ?>">
                <p class="mb-3"><?= esc(lang('Devtools.packages.sign.package')) ?> <strong class="font-monospace"><?= esc($file) ?></strong></p>
                <div class="mb-3">
                    <label class="form-label" for="secret-key"><?= esc(lang('Devtools.packages.sign.key')) ?></label>
                    <textarea class="form-control font-monospace" id="secret-key" name="secret_key" rows="3" autocomplete="off" spellcheck="false" autocapitalize="off" autocorrect="off" aria-describedby="secret-key-help"></textarea>
                    <div class="form-text" id="secret-key-help"><?= esc(lang('Devtools.packages.sign.keyHelp')) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="secret-key-file"><?= esc(lang('Devtools.packages.sign.keyFile')) ?></label>
                    <input class="form-control" type="file" id="secret-key-file" name="secret_key_file" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="key-password"><?= esc(lang('Devtools.packages.sign.password')) ?></label>
                    <input class="form-control" type="password" id="key-password" name="password" autocomplete="off" aria-describedby="key-password-help">
                    <div class="form-text" id="key-password-help"><?= esc(lang('Devtools.packages.sign.passwordHelp')) ?></div>
                </div>
            </div>
            <div class="card-footer d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-pen" aria-hidden="true"></i> <?= esc(lang('Devtools.packages.sign.submit')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= esc(backend_url('devtools/packages'), 'attr') ?>"><?= esc(lang('Devtools.packages.sign.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
