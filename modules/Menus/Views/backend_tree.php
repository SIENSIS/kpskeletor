<?php
/**
 * Entries of the back-office menu (recursive) for the customisation editor.
 *
 * @var list<array<string, mixed>> $nodes
 * @var string|null                $scope
 * @var bool                       $canCustomise
 * @var bool                       $canLinks
 * @var bool                       $canReset
 */
$query = $scope === null ? '' : '&group=' . rawurlencode($scope);
?>
<?php foreach ($nodes as $node): ?>
    <?php
    $key    = (string) $node['key'];
    $custom = str_starts_with($key, 'custom.');
    ?>
    <li class="menu-tree-item<?= $node['hidden'] ? ' menu-tree-hidden' : '' ?>" data-tree-item data-key="<?= esc($key, 'attr') ?>">
        <div class="menu-tree-row d-flex flex-wrap align-items-center gap-2">
            <?php if ($canCustomise): ?>
                <button type="button" class="btn btn-sm btn-light menu-tree-handle d-none" data-tree-handle aria-label="<?= esc(lang('Menus.tree.handle', [(string) $node['text']]), 'attr') ?>" title="<?= esc(lang('Menus.tree.handleHelp'), 'attr') ?>"><i class="bi bi-grip-vertical" aria-hidden="true"></i></button>
            <?php endif ?>
            <span class="flex-grow-1 menu-tree-label">
                <i class="<?= esc((string) $node['icon'], 'attr') ?> me-1" aria-hidden="true"></i>
                <strong><?= esc((string) $node['text']) ?></strong>
                <?php if ($custom): ?>
                    <?= ui_badge(lang($node['kind'] === 'group' ? 'Menus.backend.customGroup' : 'Menus.backend.customLink'), 'primary') ?>
                <?php else: ?>
                    <?= ui_badge((string) $node['module'], 'light') ?>
                <?php endif ?>
                <?php if ($node['hidden']): ?>
                    <?= ui_badge(lang('Menus.backend.hidden'), 'secondary', 'bi bi-eye-slash') ?>
                <?php endif ?>
                <?php if ($node['customised']): ?>
                    <?= ui_badge(lang('Menus.backend.customised'), 'info', 'bi bi-brush') ?>
                <?php endif ?>
                <?php if ($node['broken']): ?>
                    <?= ui_badge(lang('Menus.items.broken'), 'warning', 'bi bi-exclamation-triangle') ?>
                <?php endif ?>
                <?php if ($node['permission'] !== ''): ?>
                    <code class="small ms-1"><?= esc((string) $node['permission']) ?></code>
                <?php endif ?>
            </span>
            <?php if ($canCustomise): ?>
                <?= theme_view('Modules\Menus\Views\tree_buttons', ['moveKey' => $key, 'label' => (string) $node['text']]) ?>
                <a class="btn btn-sm btn-outline-primary" href="<?= esc(site_url('backend/menus/backend/entry') . '?key=' . rawurlencode($key) . $query, 'attr') ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.retouch')) ?></a>
            <?php endif ?>
            <?php if ($custom && $scope === null && ($node['kind'] === 'group' ? $canCustomise : $canLinks)): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= esc(site_url('backend/menus/backend/custom/' . (int) substr($key, 7)), 'attr') ?>"><i class="bi bi-gear me-1" aria-hidden="true"></i><?= esc(lang($node['kind'] === 'group' ? 'Menus.custom.editGroup' : 'Menus.custom.editLink')) ?></a>
            <?php endif ?>
            <?php if ($canReset && $node['customised']): ?>
                <button type="submit" class="btn btn-sm btn-outline-warning" name="key" value="<?= esc($key, 'attr') ?>" formaction="<?= esc(site_url('backend/menus/backend/reset'), 'attr') ?>" data-confirm="<?= esc(lang('Menus.backend.resetEntryConfirm', [(string) $node['text']]), 'attr') ?>"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.reset')) ?></button>
            <?php endif ?>
        </div>
        <ul class="menu-tree-list list-unstyled" data-tree-list>
            <?= theme_view('Modules\Menus\Views\backend_tree', ['nodes' => $node['children'], 'scope' => $scope, 'canCustomise' => $canCustomise, 'canLinks' => $canLinks, 'canReset' => $canReset]) ?>
        </ul>
    </li>
<?php endforeach ?>
