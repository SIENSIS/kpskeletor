<?php
/**
 * Temporary home page of the public site (the Pages module replaces it with the home page
 * blocks).
 *
 * @var string $title
 */
?>
<?= $this->extend(theme_layout('public')) ?>
<?= $this->section('content') ?>
<div class="py-5 text-center">
    <i class="bi bi-grid-3x3-gap-fill display-4 text-primary" aria-hidden="true"></i>
    <h1 class="display-6 mt-3"><?= esc($title) ?></h1>
    <p class="lead text-body-secondary col-lg-8 mx-auto"><?= esc(lang('Core.home.text')) ?></p>
</div>
<?= $this->endSection() ?>
