<?php
/**
 * Badge of a notice priority (info, warning, critical).
 *
 * @var string $priority
 */
$variants = ['info' => 'info', 'warning' => 'warning', 'critical' => 'danger'];
$icons    = ['info' => 'bi bi-info-circle', 'warning' => 'bi bi-exclamation-triangle', 'critical' => 'bi bi-exclamation-octagon'];
?>
<?= ui_badge(lang('News.priorities.' . (isset($variants[$priority]) ? $priority : 'info')), $variants[$priority] ?? 'info', $icons[$priority] ?? 'bi bi-info-circle') ?>
