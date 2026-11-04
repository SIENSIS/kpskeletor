<?php
/**
 * Sample back-office menu of the theme preview (the menu builder fills the region later).
 */
$items = [
    ['bi-speedometer2', 'Core.preview.menu.dashboard', true],
    ['bi-newspaper', 'Core.preview.menu.news', false],
    ['bi-calendar3', 'Core.preview.menu.events', false],
    ['bi-folder2-open', 'Core.preview.menu.files', false],
];
?>
<ul class="nav flex-column gap-1">
    <?php foreach ($items as [$icon, $label, $active]): ?>
        <li class="nav-item"><a class="nav-link<?= $active ? ' active' : '' ?>" href="#" title="<?= esc(lang($label)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="bi <?= $icon ?>" aria-hidden="true"></i><span class="nav-text"><?= esc(lang($label)) ?></span></a></li>
    <?php endforeach ?>
    <li class="nav-heading"><?= esc(lang('Core.preview.menu.admin')) ?></li>
    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-people" aria-hidden="true"></i><span class="nav-text"><?= esc(lang('Core.preview.menu.users')) ?></span></a></li>
    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-gear" aria-hidden="true"></i><span class="nav-text"><?= esc(lang('Core.preview.menu.settings')) ?></span></a></li>
</ul>
