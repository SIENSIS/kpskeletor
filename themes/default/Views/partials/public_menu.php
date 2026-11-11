<?php
/**
 * A public menu location (`menu('<location>')`, docs/menus.md): `primary` as the navbar of the
 * header (items with children become dropdowns), any other location as a plain list of links.
 * Items are already filtered for the visitor; new-tab links get `rel="noopener noreferrer"`.
 *
 * @var string $location
 */
$items  = menu($location);
$newTab = ' target="_blank" rel="noopener noreferrer"';
?>
<?php if ($items !== []): ?>
    <?php if ($location === 'primary'): ?>
        <ul class="navbar-nav me-auto mb-2 mb-md-0 public-menu" data-menu="<?= esc($location, 'attr') ?>">
            <?php foreach ($items as $item): ?>
                <?php $classes = implode(' ', array_filter(['nav-item', $item->cssClass, $item->hasChildren() ? 'dropdown' : ''])); ?>
                <li class="<?= esc($classes, 'attr') ?>">
                    <?php if ($item->hasChildren()): ?>
                        <a class="nav-link dropdown-toggle<?= $item->open ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?php if ($item->icon !== ''): ?><i class="<?= esc($item->icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($item->label) ?></a>
                        <ul class="dropdown-menu">
                            <?php if ($item->url !== null): ?>
                                <li><a class="dropdown-item<?= $item->active ? ' active' : '' ?>" href="<?= esc($item->url, 'attr') ?>"<?= $item->active ? ' aria-current="page"' : '' ?><?= $item->newTab() ? $newTab : '' ?>><?= esc($item->label) ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php endif ?>
                            <?php foreach ($item->children as $child): ?>
                                <?php if ($child->url !== null): ?>
                                    <li><a class="dropdown-item <?= esc($child->cssClass, 'attr') ?><?= $child->active ? ' active' : '' ?>" href="<?= esc($child->url, 'attr') ?>"<?= $child->active ? ' aria-current="page"' : '' ?><?= $child->newTab() ? $newTab : '' ?>><?php if ($child->icon !== ''): ?><i class="<?= esc($child->icon, 'attr') ?> me-2" aria-hidden="true"></i><?php endif ?><?= esc($child->label) ?></a></li>
                                <?php else: ?>
                                    <li><h6 class="dropdown-header"><?= esc($child->label) ?></h6></li>
                                <?php endif ?>
                                <?php foreach ($child->children as $grandchild): ?>
                                    <?php if ($grandchild->url !== null): ?>
                                        <li><a class="dropdown-item ps-4 <?= esc($grandchild->cssClass, 'attr') ?><?= $grandchild->active ? ' active' : '' ?>" href="<?= esc($grandchild->url, 'attr') ?>"<?= $grandchild->active ? ' aria-current="page"' : '' ?><?= $grandchild->newTab() ? $newTab : '' ?>><?= esc($grandchild->label) ?></a></li>
                                    <?php endif ?>
                                <?php endforeach ?>
                            <?php endforeach ?>
                        </ul>
                    <?php elseif ($item->url !== null): ?>
                        <a class="nav-link<?= $item->active ? ' active' : '' ?>" href="<?= esc($item->url, 'attr') ?>"<?= $item->active ? ' aria-current="page"' : '' ?><?= $item->newTab() ? $newTab : '' ?>><?php if ($item->icon !== ''): ?><i class="<?= esc($item->icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($item->label) ?></a>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
    <?php else: ?>
        <ul class="list-inline mb-0 public-menu public-menu-<?= esc($location, 'attr') ?>" data-menu="<?= esc($location, 'attr') ?>">
            <?php foreach ($items as $item): ?>
                <?php foreach ([$item, ...$item->children] as $link): ?>
                    <?php if ($link->url !== null): ?>
                        <li class="list-inline-item <?= esc($link->cssClass, 'attr') ?>"><a class="link-secondary<?= $link->active ? ' fw-semibold' : '' ?>" href="<?= esc($link->url, 'attr') ?>"<?= $link->active ? ' aria-current="page"' : '' ?><?= $link->newTab() ? $newTab : '' ?>><?php if ($link->icon !== ''): ?><i class="<?= esc($link->icon, 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($link->label) ?></a></li>
                    <?php endif ?>
                <?php endforeach ?>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
<?php endif ?>
