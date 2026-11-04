<?php
/**
 * Tabs (Bootstrap nav-tabs with panes).
 *
 * @var string                                                                             $id    unique prefix for the ids
 * @var list<array{id: string, label: string, content: string, icon?: string|null, active?: bool}> $tabs content is HTML (already escaped)
 */
$tabs ??= [];
$prefix = preg_replace('/[^a-z0-9_-]/i', '', $id ?? 'tabs') ?: 'tabs';
$active = 0;
foreach ($tabs as $i => $tab) {
    if (! empty($tab['active'])) {
        $active = $i;
    }
}
?>
<div class="tabs-component" data-tabs="<?= esc($prefix) ?>">
    <ul class="nav nav-tabs flex-nowrap overflow-x-auto" role="tablist">
        <?php foreach ($tabs as $i => $tab): ?>
            <?php $tabId = $prefix . '-' . (preg_replace('/[^a-z0-9_-]/i', '', $tab['id']) ?: (string) $i); ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-nowrap<?= $i === $active ? ' active' : '' ?>" id="<?= esc($tabId) ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= esc($tabId) ?>" type="button" role="tab" aria-controls="<?= esc($tabId) ?>" aria-selected="<?= $i === $active ? 'true' : 'false' ?>">
                    <?php if (ui_icon($tab['icon'] ?? null) !== ''): ?><i class="<?= esc(ui_icon($tab['icon'] ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($tab['label']) ?>
                </button>
            </li>
        <?php endforeach ?>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3">
        <?php foreach ($tabs as $i => $tab): ?>
            <?php $tabId = $prefix . '-' . (preg_replace('/[^a-z0-9_-]/i', '', $tab['id']) ?: (string) $i); ?>
            <div class="tab-pane fade<?= $i === $active ? ' show active' : '' ?>" id="<?= esc($tabId) ?>" role="tabpanel" aria-labelledby="<?= esc($tabId) ?>-tab" tabindex="0">
                <?= $tab['content'] ?>
            </div>
        <?php endforeach ?>
    </div>
</div>
