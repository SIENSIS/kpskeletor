<?php
/**
 * @var int $from
 * @var int $to
 * @var int $total
 */
?>
<p class="pagination-info small text-body-secondary mb-0" aria-live="polite"><?= $total === 0 ? esc(lang('Core.ui.paginationEmpty')) : esc(lang('Core.ui.paginationInfo', [$from, $to, $total])) ?></p>
