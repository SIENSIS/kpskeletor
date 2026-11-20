<?php
/**
 * `backend/devtools/packages/signed`: shown once after a signature, when the cleanup has run and
 * the session has ended (auth layout: the user is signed out). Public facts only.
 *
 * @var string                     $layout
 * @var array<string, string>|null $info
 * @var list<string>               $done
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div data-signed-result>
    <?php if (is_array($info)): ?>
        <p><?= esc(lang('Devtools.packages.signed.text', [$info['file'], $info['keyId']])) ?></p>
        <p class="small mb-1"><?= esc(lang('Devtools.packages.built.publicKey')) ?></p>
        <code class="d-block bg-body-tertiary border rounded p-2 small text-break mb-3"><?= esc($info['publicKey']) ?></code>
        <p class="small text-body-secondary">SHA-256: <span class="font-monospace text-break"><?= esc($info['sha256']) ?></span></p>
    <?php endif ?>
    <div class="alert alert-info small" role="status"><?= esc(lang('Devtools.packages.signed.cleanup', [implode(', ', $done)])) ?></div>
    <a class="btn btn-primary w-100" href="<?= esc(site_url('login'), 'attr') ?>"><?= esc(lang('Devtools.packages.signed.login')) ?></a>
</div>
<?= $this->endSection() ?>
