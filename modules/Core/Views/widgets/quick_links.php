<?php
/**
 * Widget "Quick links" (`Modules\Core\Widgets\QuickLinks`): the entries the user can open.
 *
 * @var list<array{label: string, icon: string, url: string}> $links
 */
?>
<div class="d-grid gap-2 quick-links">
    <?php foreach ($links as $link): ?>
        <a class="btn btn-outline-secondary text-start d-flex align-items-center gap-2" href="<?= esc(ui_safe_url($link['url']), 'attr') ?>">
            <i class="<?= esc(ui_icon($link['icon']) ?: 'bi bi-link-45deg', 'attr') ?>" aria-hidden="true"></i>
            <span class="text-truncate"><?= esc($link['label']) ?></span>
        </a>
    <?php endforeach ?>
</div>
