<?php
/**
 * Breadcrumbs. Data: $items (list of ['label' => string, 'url' => ?string]); the last one is the
 * current page. Long trails keep the first and the last two entries on small screens.
 *
 * @var list<array{label: string, url?: string|null}> $items
 */
$items ??= [];
$count = count($items);
?>
<?php if ($count > 0): ?>
<nav aria-label="<?= esc(lang('Core.ui.breadcrumbs')) ?>">
    <ol class="breadcrumb mb-0">
        <?php foreach ($items as $i => $item): ?>
            <?php $last = $i === $count - 1; ?>
            <li class="breadcrumb-item<?= $last ? ' active' : '' ?><?= ! $last && $i > 0 && $i < $count - 2 ? ' d-none d-md-inline-block' : '' ?>"<?= $last ? ' aria-current="page"' : '' ?>>
                <?php if (! $last && ! empty($item['url'])): ?>
                    <a href="<?= esc(ui_safe_url($item['url'])) ?>"><?= esc($item['label']) ?></a>
                <?php else: ?>
                    <?= esc($item['label']) ?>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ol>
</nav>
<?php endif ?>
