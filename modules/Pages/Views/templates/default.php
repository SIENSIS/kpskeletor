<?php
/**
 * Page template "default": the article in a readable column.
 *
 * @var string $layout
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="page-column mx-auto">
    <?= $this->include(theme_resolve('Modules\Pages\Views\partials\article')) ?>
</div>
<?= $this->endSection() ?>
