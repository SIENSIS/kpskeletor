<?php
/**
 * Page template "wide": the article over the whole width of the content area.
 *
 * @var string $layout
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include(theme_resolve('Modules\Pages\Views\partials\article')) ?>
<?= $this->endSection() ?>
