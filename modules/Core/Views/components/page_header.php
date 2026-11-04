<?php
/**
 * Page header: title, optional subtitle and icon, breadcrumbs and action buttons.
 *
 * @var string                                                                                      $title
 * @var string|null                                                                                 $subtitle
 * @var string|null                                                                                 $icon
 * @var list<array{label: string, url?: string|null}>                                               $breadcrumbs
 * @var list<array{label: string, url: string, icon?: string|null, variant?: string|null}>          $actions
 */
$breadcrumbs ??= [];
$actions ??= [];
?>
<header class="page-header mb-4">
    <?= theme_component('breadcrumbs', ['items' => $breadcrumbs]) ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
        <div class="min-w-0">
            <h1 class="h3 mb-0 text-truncate">
                <?php if (! empty($icon) && ui_icon($icon) !== ''): ?><i class="<?= esc(ui_icon($icon)) ?> me-2" aria-hidden="true"></i><?php endif ?><?= esc($title ?? '') ?>
            </h1>
            <?php if (! empty($subtitle)): ?>
                <p class="text-body-secondary mb-0"><?= esc($subtitle) ?></p>
            <?php endif ?>
        </div>
        <?php if ($actions !== []): ?>
            <div class="page-actions d-flex flex-wrap gap-2">
                <?php foreach ($actions as $i => $action): ?>
                    <a href="<?= esc(ui_safe_url($action['url'])) ?>" class="btn btn-<?= esc(ui_variant($action['variant'] ?? ($i === 0 ? 'primary' : 'secondary'))) ?>">
                        <?php if (ui_icon($action['icon'] ?? null) !== ''): ?><i class="<?= esc(ui_icon($action['icon'] ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($action['label']) ?>
                    </a>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </div>
</header>
