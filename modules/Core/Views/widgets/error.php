<?php
/**
 * Shown instead of a widget that failed (the error is in the application log).
 */
?>
<div class="d-flex align-items-start gap-2 text-danger-emphasis py-2" role="alert">
    <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
    <span><?= esc(lang('Core.widgets.failed')) ?></span>
</div>
