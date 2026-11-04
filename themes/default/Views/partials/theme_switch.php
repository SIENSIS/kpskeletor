<?php
/**
 * Light / dark / auto switch (core/ui/theme-switch.js). Hidden when the theme forces a mode or
 * the `modeSwitch` option is off.
 *
 * @var string|null $switchClass
 */
if (! theme_mode()->switchVisible()) {
    return;
}
$current = theme_mode()->current();
$modes   = ['light' => 'bi-sun', 'dark' => 'bi-moon-stars', 'auto' => 'bi-circle-half'];
?>
<div class="dropdown theme-switch <?= esc($switchClass ?? '') ?>" data-theme-switch>
    <button class="btn btn-link nav-link text-body px-2 d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= esc(lang('Core.mode.label')) ?>" title="<?= esc(lang('Core.mode.label')) ?>">
        <i class="bi <?= esc($modes[$current]) ?>" data-theme-switch-icon aria-hidden="true"></i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($modes as $mode => $icon): ?>
            <li>
                <button type="button" class="dropdown-item d-flex align-items-center gap-2<?= $mode === $current ? ' active' : '' ?>" data-theme-mode-value="<?= esc($mode) ?>" aria-pressed="<?= $mode === $current ? 'true' : 'false' ?>">
                    <i class="bi <?= esc($icon) ?>" aria-hidden="true"></i><?= esc(lang('Core.mode.' . $mode)) ?>
                </button>
            </li>
        <?php endforeach ?>
    </ul>
</div>
