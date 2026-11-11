<?php
/**
 * The tree editor of a public menu's items (drag and drop, keyboard, buttons).
 *
 * @var string                                             $layout
 * @var array{id: int, name: string, location: string|null} $menu
 * @var list<array<string, mixed>>                         $tree
 * @var array<string, string>                              $types
 * @var list<int>                                          $broken
 * @var string                                             $orderUrl
 * @var string                                             $newUrl
 * @var string                                             $baseUrl
 * @var int                                                $maxDepth
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-primary" href="<?= esc($newUrl, 'attr') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.items.add')) ?></a>
    <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend/menus/public'), 'attr') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Menus.public.back')) ?></a>
</div>
<?php if ($tree === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-diagram-3', 'title' => lang('Menus.items.none'), 'message' => lang('Menus.items.noneHelp')]) ?>
<?php else: ?>
    <form method="post" action="<?= esc($orderUrl, 'attr') ?>" class="card shadow-sm" data-component="menus/tree-editor" data-mode="public" data-max-depth="<?= (int) $maxDepth ?>" data-moved-text="<?= esc(lang('Menus.tree.moved'), 'attr') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tree" value="" data-tree-input>
        <div class="card-body">
            <p class="small text-body-secondary"><?= esc(lang('Menus.tree.help')) ?></p>
            <ul class="menu-tree list-unstyled mb-0" data-tree-list data-tree-root data-zone="menu">
                <?= theme_view('Modules\Menus\Views\items_tree', ['nodes' => $tree, 'types' => $types, 'broken' => $broken, 'baseUrl' => $baseUrl, 'orderUrl' => $orderUrl]) ?>
            </ul>
            <p class="visually-hidden" aria-live="polite" data-tree-status></p>
        </div>
        <div class="card-footer d-none" data-tree-save>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.tree.save')) ?></button>
            <span class="small text-body-secondary ms-2"><?= esc(lang('Menus.tree.unsaved')) ?></span>
        </div>
    </form>
<?php endif ?>
<?= $this->endSection() ?>
