<?php
/**
 * Theme preview of the public, auth, blank and print layouts.
 *
 * @var string                                  $layout
 * @var list<array{slug: string, name: string}> $themes
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<article>
    <h1 class="display-6"><?= esc(lang('Core.preview.sampleTitle')) ?></h1>
    <p class="lead text-body-secondary"><?= esc(lang('Core.preview.tabText')) ?></p>
    <p><?= ui_date('2026-11-02 10:30:00', 'long') ?> · <?= ui_badge(lang('Core.preview.badge'), 'primary') ?></p>
    <p><a href="#" class="btn btn-primary"><?= esc(lang('Core.preview.readMore')) ?></a> <a href="#" class="btn btn-outline-primary"><?= esc(lang('Core.preview.contact')) ?></a></p>
    <p class="small text-body-secondary mb-0"><?= esc(lang('Core.preview.chain', [implode(' → ', array_column($themes, 'slug'))])) ?></p>
</article>
<?= $this->endSection() ?>
