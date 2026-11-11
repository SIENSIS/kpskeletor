<?php
/**
 * The customisation editor of the back-office menu: scope (everyone or a group), the generated
 * entries and the custom ones per zone in a tree editor, resets and orphaned customisations.
 *
 * @var string                                                                       $layout
 * @var string|null                                                                  $scope
 * @var array<string, array{title: string}>                                          $groups
 * @var array<string, list<array<string, mixed>>>                                    $tree
 * @var int                                                                          $scopeCount
 * @var list<array{id: int, key: string, scope: string|null, updated_at: string|null}> $orphans
 * @var bool                                                                         $canCustomise
 * @var bool                                                                         $canLinks
 * @var bool                                                                         $canReset
 * @var array<string, string>                                                        $zones
 */
$scopeName = $scope === null ? lang('Menus.backend.everyone') : $groups[$scope]['title'];
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<nav aria-label="<?= esc(lang('Menus.backend.scopes'), 'attr') ?>" class="mb-3">
    <ul class="nav nav-pills flex-wrap gap-1">
        <li class="nav-item"><a class="nav-link<?= $scope === null ? ' active' : '' ?>" href="<?= esc(site_url('backend/menus/backend'), 'attr') ?>"<?= $scope === null ? ' aria-current="page"' : '' ?>><?= esc(lang('Menus.backend.everyone')) ?></a></li>
        <?php foreach ($groups as $name => $group): ?>
            <li class="nav-item"><a class="nav-link<?= $scope === $name ? ' active' : '' ?>" href="<?= esc(site_url('backend/menus/backend') . '?group=' . rawurlencode($name), 'attr') ?>"<?= $scope === $name ? ' aria-current="page"' : '' ?>><?= esc($group['title']) ?></a></li>
        <?php endforeach ?>
    </ul>
</nav>
<p class="text-body-secondary small"><?= esc(lang($scope === null ? 'Menus.backend.helpEveryone' : 'Menus.backend.helpGroup', [$scopeName])) ?></p>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php if ($canCustomise && $scope === null): ?>
        <a class="btn btn-outline-primary" href="<?= esc(site_url('backend/menus/backend/group/new'), 'attr') ?>"><i class="bi bi-folder-plus me-1" aria-hidden="true"></i><?= esc(lang('Menus.custom.addGroup')) ?></a>
    <?php endif ?>
    <?php if ($canLinks && $scope === null): ?>
        <a class="btn btn-outline-primary" href="<?= esc(site_url('backend/menus/backend/link/new'), 'attr') ?>"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i><?= esc(lang('Menus.custom.addLink')) ?></a>
    <?php endif ?>
    <?php if ($canReset): ?>
        <form method="post" action="<?= esc(site_url('backend/menus/backend/reset'), 'attr') ?>" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="group" value="<?= esc((string) $scope, 'attr') ?>">
            <button type="submit" class="btn btn-outline-warning" name="reset" value="scope" data-confirm="<?= esc(lang('Menus.backend.resetScopeConfirm', [$scopeName]), 'attr') ?>"<?= $scopeCount === 0 ? ' disabled' : '' ?>><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.resetScope', [$scopeName])) ?></button>
            <button type="submit" class="btn btn-outline-danger" name="reset" value="everything" data-confirm="<?= esc(lang('Menus.backend.resetEverythingConfirm'), 'attr') ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.resetEverything')) ?></button>
        </form>
    <?php endif ?>
</div>
<form method="post" action="<?= esc(site_url('backend/menus/backend/order'), 'attr') ?>" class="card shadow-sm mb-4"<?= $canCustomise ? ' data-component="menus/tree-editor" data-mode="backend" data-max-depth="4" data-moved-text="' . esc(lang('Menus.tree.moved'), 'attr') . '"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="group" value="<?= esc((string) $scope, 'attr') ?>">
    <input type="hidden" name="reset" value="entry">
    <input type="hidden" name="tree" value="" data-tree-input>
    <div class="card-body">
        <?php if ($canCustomise): ?>
            <p class="small text-body-secondary"><?= esc(lang('Menus.tree.helpBackend')) ?></p>
        <?php endif ?>
        <?php foreach ($tree as $zone => $nodes): ?>
            <h2 class="h6 mt-3" id="zone-<?= esc($zone, 'attr') ?>"><?= esc($zones[$zone] ?? $zone) ?></h2>
            <ul class="menu-tree list-unstyled mb-0" data-tree-list data-tree-root data-zone="<?= esc($zone, 'attr') ?>" aria-labelledby="zone-<?= esc($zone, 'attr') ?>">
                <?= theme_view('Modules\Menus\Views\backend_tree', ['nodes' => $nodes, 'scope' => $scope, 'canCustomise' => $canCustomise, 'canLinks' => $canLinks, 'canReset' => $canReset]) ?>
            </ul>
        <?php endforeach ?>
        <p class="visually-hidden" aria-live="polite" data-tree-status></p>
    </div>
    <div class="card-footer d-none" data-tree-save>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.tree.save')) ?></button>
        <span class="small text-body-secondary ms-2"><?= esc(lang('Menus.tree.unsaved')) ?></span>
    </div>
</form>
<?php if ($orphans !== []): ?>
    <section class="card shadow-sm border-warning" aria-labelledby="orphans-title">
        <div class="card-header"><h2 class="h6 mb-0" id="orphans-title"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.orphans')) ?></h2></div>
        <form method="post" action="<?= esc(site_url('backend/menus/backend/orphans'), 'attr') ?>">
            <?= csrf_field() ?>
            <div class="card-body">
                <p class="small text-body-secondary"><?= esc(lang('Menus.backend.orphansHelp')) ?></p>
                <?php foreach ($orphans as $orphan): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="orphan-<?= $orphan['id'] ?>" name="ids[]" value="<?= $orphan['id'] ?>" checked>
                        <label class="form-check-label" for="orphan-<?= $orphan['id'] ?>"><code><?= esc($orphan['key']) ?></code> · <?= esc($orphan['scope'] ?? lang('Menus.backend.everyone')) ?> · <?= ui_date($orphan['updated_at'], 'relative') ?></label>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-outline-danger" data-confirm="<?= esc(lang('Menus.backend.orphansConfirm'), 'attr') ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.orphansDelete')) ?></button>
            </div>
        </form>
    </section>
<?php endif ?>
<?= $this->endSection() ?>
