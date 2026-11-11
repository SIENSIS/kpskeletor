<?php
/**
 * Entries of the back-office menu (recursive). Data: $entries (list of MenuEntry), $depth.
 *
 * @var list<Modules\Core\Menus\MenuEntry> $entries
 * @var int                                $depth
 */
$depth ??= 0;
$newTab = ' target="_blank" rel="noopener noreferrer"';
?>
<?php foreach ($entries as $entry): ?>
    <?php $id = 'menu-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($entry->key)); ?>
    <li class="nav-item">
        <?php if ($entry->hasChildren()): ?>
            <div class="d-flex align-items-center">
                <?php if ($entry->url() !== null): ?>
                    <a class="nav-link flex-grow-1<?= $entry->active ? ' active' : '' ?>" href="<?= esc($entry->url()) ?>" title="<?= esc($entry->text()) ?>"<?= $entry->active ? ' aria-current="page"' : '' ?><?= $entry->newTab() ? $newTab : '' ?>><i class="<?= esc($entry->icon) ?>" aria-hidden="true"></i><span class="nav-text"><?= esc($entry->text()) ?></span></a>
                    <button class="btn btn-link nav-toggle<?= $entry->open ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($id) ?>" aria-controls="<?= esc($id) ?>" aria-expanded="<?= $entry->open ? 'true' : 'false' ?>" aria-label="<?= esc(lang('Core.menu.toggle', [$entry->text()])) ?>"><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                <?php else: ?>
                    <button class="nav-link nav-group flex-grow-1 text-start<?= $entry->open ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($id) ?>" aria-controls="<?= esc($id) ?>" aria-expanded="<?= $entry->open ? 'true' : 'false' ?>" title="<?= esc($entry->text()) ?>"><i class="<?= esc($entry->icon) ?>" aria-hidden="true"></i><span class="nav-text"><?= esc($entry->text()) ?></span><i class="bi bi-chevron-down ms-auto nav-text" aria-hidden="true"></i></button>
                <?php endif ?>
            </div>
            <ul class="nav flex-column gap-1 collapse submenu<?= $entry->open ? ' show' : '' ?>" id="<?= esc($id) ?>">
                <?= theme_partial('sidebar_menu_items', ['entries' => $entry->children, 'depth' => $depth + 1]) ?>
            </ul>
        <?php else: ?>
            <a class="nav-link<?= $entry->active ? ' active' : '' ?>" href="<?= esc((string) $entry->url()) ?>" title="<?= esc($entry->text()) ?>"<?= $entry->active ? ' aria-current="page"' : '' ?><?= $entry->newTab() ? $newTab : '' ?>><i class="<?= esc($entry->icon) ?>" aria-hidden="true"></i><span class="nav-text"><?= esc($entry->text()) ?></span></a>
        <?php endif ?>
    </li>
<?php endforeach ?>
