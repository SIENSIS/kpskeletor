<?php
/**
 * Empty state: an icon, a title, a message and an optional action.
 *
 * @var string|null                                            $icon
 * @var string|null                                            $title
 * @var string|null                                            $message
 * @var array{label: string, url: string, icon?: string}|null $action
 * @var bool|null                                              $compact Smaller, for cards and widgets
 */
$iconClass = ui_icon($icon ?? 'bi-inbox') ?: 'bi bi-inbox';
$compact   = ! empty($compact);
?>
<div class="empty-state text-center <?= $compact ? 'empty-state-compact py-3' : 'py-5' ?> px-3">
    <i class="<?= esc($iconClass) ?> empty-state-icon text-body-tertiary" aria-hidden="true"></i>
    <<?= $compact ? 'p class="h6 mt-2 mb-1"' : 'h2 class="h5 mt-3"' ?>><?= esc($title ?? lang('Core.ui.emptyTitle')) ?></<?= $compact ? 'p' : 'h2' ?>>
    <?php if (! empty($message)): ?>
        <p class="text-body-secondary mb-3"><?= esc($message) ?></p>
    <?php endif ?>
    <?php if (! empty($action)): ?>
        <a href="<?= esc(ui_safe_url($action['url'])) ?>" class="btn btn-primary">
            <?php if (ui_icon($action['icon'] ?? null) !== ''): ?><i class="<?= esc(ui_icon($action['icon'] ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($action['label']) ?>
        </a>
    <?php endif ?>
</div>
