<?php
/**
 * @var string $status  machine value (data attribute)
 * @var string $label   text shown
 * @var string $variant Bootstrap colour
 */
?>
<span class="status badge rounded-pill bg-<?= esc($variant) ?>-subtle text-<?= esc($variant) ?>-emphasis border border-<?= esc($variant) ?>-subtle" data-status="<?= esc($status) ?>"><i class="bi bi-circle-fill status-dot me-1" aria-hidden="true"></i><?= esc($label) ?></span>
