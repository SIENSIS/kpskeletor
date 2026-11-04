<?php
/**
 * @var string      $label
 * @var string      $url
 * @var string|null $icon
 * @var bool        $external
 */
?>
<a href="<?= esc(ui_safe_url($url)) ?>"<?= $external ? ' rel="noopener noreferrer" target="_blank"' : '' ?>><?php if (ui_icon($icon ?? null) !== ''): ?><i class="<?= esc(ui_icon($icon ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($label) ?><?php if ($external): ?><i class="bi bi-box-arrow-up-right ms-1 small" aria-hidden="true"></i><span class="visually-hidden"> (<?= esc(lang('Core.ui.newWindow')) ?>)</span><?php endif ?></a>
