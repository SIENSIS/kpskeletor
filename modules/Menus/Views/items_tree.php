<?php
/**
 * Items of a public menu (recursive) for the tree editor.
 *
 * @var list<array<string, mixed>> $nodes
 * @var array<string, string>      $types
 * @var list<int>                  $broken
 * @var string                     $baseUrl
 * @var string                     $orderUrl
 */
?>
<?php foreach ($nodes as $node): ?>
    <?php $id = (int) $node['id']; ?>
    <li class="menu-tree-item" data-tree-item data-key="<?= $id ?>" id="item-<?= $id ?>">
        <div class="menu-tree-row d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-light menu-tree-handle d-none" data-tree-handle aria-label="<?= esc(lang('Menus.tree.handle', [(string) $node['text']]), 'attr') ?>" title="<?= esc(lang('Menus.tree.handleHelp'), 'attr') ?>"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
            <span class="flex-grow-1 menu-tree-label">
                <?php if ($node['icon'] !== ''): ?><i class="<?= esc((string) $node['icon'], 'attr') ?> me-1" aria-hidden="true"></i><?php endif ?>
                <strong><?= esc((string) $node['text']) ?></strong>
                <span class="small text-body-secondary ms-1"><?= esc($types[$node['type']] ?? (string) $node['type']) ?><?= in_array($node['type'], ['url', 'route'], true) ? ': ' . esc((string) $node['value']) : '' ?></span>
                <?php if ($node['visibility'] !== 'all'): ?>
                    <?= ui_badge(lang('Menus.visibility.' . $node['visibility']), 'info', 'bi bi-eye') ?>
                <?php endif ?>
                <?php if ($node['target'] === '_blank'): ?>
                    <?= ui_badge(lang('Menus.items.newTab'), 'light', 'bi bi-box-arrow-up-right') ?>
                <?php endif ?>
                <?php if (in_array($id, $broken, true)): ?>
                    <?= ui_badge(lang('Menus.items.broken'), 'warning', 'bi bi-exclamation-triangle') ?>
                <?php endif ?>
            </span>
            <?= theme_view('Modules\Menus\Views\tree_buttons', ['moveKey' => (string) $id, 'label' => (string) $node['text']]) ?>
            <a class="btn btn-sm btn-outline-primary" href="<?= esc($baseUrl . '/edit/' . $id, 'attr') ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= esc(lang('Menus.edit')) ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="<?= esc($baseUrl . '/new?parent=' . $id, 'attr') ?>" title="<?= esc(lang('Menus.items.addChild'), 'attr') ?>" aria-label="<?= esc(lang('Menus.items.addChild') . ': ' . $node['text'], 'attr') ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i></a>
            <button type="submit" class="btn btn-sm btn-outline-danger" formaction="<?= esc($baseUrl . '/delete/' . $id, 'attr') ?>" data-confirm="<?= esc(lang('Menus.items.deleteConfirm', [(string) $node['text']]), 'attr') ?>" aria-label="<?= esc(lang('Menus.delete') . ': ' . $node['text'], 'attr') ?>" title="<?= esc(lang('Menus.delete'), 'attr') ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
        </div>
        <ul class="menu-tree-list list-unstyled" data-tree-list>
            <?= theme_view('Modules\Menus\Views\items_tree', ['nodes' => $node['children'], 'types' => $types, 'broken' => $broken, 'baseUrl' => $baseUrl, 'orderUrl' => $orderUrl]) ?>
        </ul>
    </li>
<?php endforeach ?>
