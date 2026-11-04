<?php
/**
 * Main screen of the {{Name}} module, rendered in the back-office layout of the active theme.
 * A theme can override this view in themes/<theme>/Views/overrides/{{Name}}/index.php.
 *
 * The layout prints the page header (title and breadcrumbs, see BackendController::render()).
 *
 * @var string $title
 * @var string $layout
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= theme_component('empty_state', ['icon' => 'bi-box', 'title' => $title, 'message' => lang('{{Name}}.intro')]) ?>
<?= $this->endSection() ?>
