<?php
/**
 * Card: optional header (icon, title, actions), body and footer.
 *
 * @var string|null                                                    $title
 * @var string|null                                                    $icon
 * @var string                                                         $body   HTML (already escaped)
 * @var string|null                                                    $footer HTML (already escaped)
 * @var list<array{label: string, url: string, icon?: string|null}>    $actions
 */
$actions ??= [];
?>
<section class="card shadow-sm">
    <?php if (! empty($title) || $actions !== []): ?>
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <h2 class="h6 mb-0">
                <?php if (ui_icon($icon ?? null) !== ''): ?><i class="<?= esc(ui_icon($icon ?? null)) ?> me-2" aria-hidden="true"></i><?php endif ?><?= esc($title ?? '') ?>
            </h2>
            <?php foreach ($actions as $action): ?>
                <a href="<?= esc(ui_safe_url($action['url'])) ?>" class="btn btn-sm btn-outline-secondary">
                    <?php if (ui_icon($action['icon'] ?? null) !== ''): ?><i class="<?= esc(ui_icon($action['icon'] ?? null)) ?> me-1" aria-hidden="true"></i><?php endif ?><?= esc($action['label']) ?>
                </a>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <div class="card-body"><?= $body ?? '' ?></div>
    <?php if (! empty($footer)): ?>
        <div class="card-footer"><?= $footer ?></div>
    <?php endif ?>
</section>
