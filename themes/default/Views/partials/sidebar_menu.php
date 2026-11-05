<?php
/**
 * The generated back-office menu in the sidebar (docs/specification.md, "Menus"): the `sidebar`
 * zone, then the `settings` zone under a heading. Entries are already filtered by permission;
 * an entry with children is a collapsible group (open when it contains the active entry).
 * Labels go in `<span class="nav-text">` (icon-only bar, decision D-04-07).
 */
$zones = ['sidebar' => null, 'settings' => 'Core.menu.settings'];
?>
<?php foreach ($zones as $zone => $heading): ?>
    <?php $entries = backend_menu($zone); ?>
    <?php if ($entries !== []): ?>
        <ul class="nav flex-column gap-1 backend-menu" data-menu-zone="<?= esc($zone) ?>">
            <?php if ($heading !== null): ?>
                <li class="nav-heading"><?= esc(lang($heading)) ?></li>
            <?php endif ?>
            <?= theme_partial('sidebar_menu_items', ['entries' => $entries, 'depth' => 0]) ?>
        </ul>
    <?php endif ?>
<?php endforeach ?>
