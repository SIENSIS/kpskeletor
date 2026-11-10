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
<?php /* Example ES module (Assets/js/{{name}}.js), mounted by core/components.js; never inline scripts. */ ?>
<p class="text-body-secondary small" data-component="{{name}}/{{name}}" data-options="<?= esc(json_encode(['message' => lang('{{Name}}.component')], JSON_THROW_ON_ERROR)) ?>" hidden></p>
<?= $this->endSection() ?>
