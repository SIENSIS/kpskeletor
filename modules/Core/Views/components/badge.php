<?php
/**
 * @var string      $text
 * @var string      $variant Bootstrap colour (checked by ui_badge())
 * @var string|null $icon
 */
?>
<span class="badge text-bg-<?= esc($variant) ?>"><?php if (ui_icon($icon ?? null) !== ''): ?><i class="<?= esc(ui_icon($icon ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($text) ?></span>
