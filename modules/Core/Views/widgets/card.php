<?php
/**
 * A dashboard widget's card (`WidgetRenderer::card()`): one grid column sized by the widget's
 * size, a header with the icon and title and the body (already escaped by the widget).
 *
 * @var string                $id
 * @var string                $title
 * @var string                $icon
 * @var string                $size
 * @var string                $columns
 * @var string                $body
 * @var bool                  $failed
 * @var array<string, string> $attributes
 */
?>
<div class="<?= esc($columns, 'attr') ?> dashboard-widget" data-widget="<?= esc($id, 'attr') ?>" data-size="<?= esc($size, 'attr') ?>"<?php foreach ($attributes as $name => $value): ?><?php if (preg_match('/^data-[a-z0-9-]+$/', $name) === 1): ?> <?= $name ?>="<?= esc($value, 'attr') ?>"<?php endif ?><?php endforeach ?>>
    <section class="card shadow-sm h-100<?= $failed ? ' border-danger-subtle' : '' ?>" aria-labelledby="widget-<?= esc(str_replace('.', '-', $id), 'attr') ?>">
        <div class="card-header d-flex align-items-center gap-2">
            <h2 class="h6 mb-0 flex-grow-1 text-truncate" id="widget-<?= esc(str_replace('.', '-', $id), 'attr') ?>">
                <?php if (ui_icon($icon) !== ''): ?><i class="<?= esc(ui_icon($icon)) ?> me-2" aria-hidden="true"></i><?php endif ?><?= esc($title) ?>
            </h2>
            <div class="dashboard-widget-tools d-flex align-items-center gap-1" data-widget-tools></div>
        </div>
        <div class="card-body dashboard-widget-body"><?= $body ?></div>
    </section>
</div>
