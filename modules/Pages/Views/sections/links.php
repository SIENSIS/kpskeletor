<?php
/**
 * Section `pages.links` (quick links): styles `icons`, `tiles` and `band`, 2–6 columns (fewer on
 * small screens). Items whose link target is gone are shown without a link.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/links.php`.
 *
 * @var array<string, mixed>                 $values  heading, intro, items (icon, image, label, link), columns
 * @var string                               $style   icons | tiles | band
 * @var \Modules\Core\Sections\RenderContext $context
 */
$columns = max(2, min(6, (int) $values['columns']));
$grid    = 'row-cols-2 row-cols-sm-' . min(3, $columns) . ' row-cols-lg-' . $columns;
?>
<?= theme_view('Modules\Pages\Views\sections\_heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => true]) ?>
<ul class="kp-links kp-links-<?= esc($style, 'attr') ?> list-unstyled row <?= esc($grid, 'attr') ?> g-3 g-lg-4 justify-content-center mb-0">
    <?php foreach ($values['items'] as $item): ?>
        <?php
        $link  = $item['link'];
        $tag   = is_array($link) ? 'a' : 'span';
        $attrs = is_array($link) ? ' href="' . esc($link['href'], 'attr') . '"' . ($link['external'] ? ' rel="noopener"' : '') : '';
        ?>
        <li class="col">
            <<?= $tag ?> class="kp-link-item"<?= $attrs ?>>
                <?php if (is_array($item['image'])): ?>
                    <span class="kp-link-media"><?= theme_view('Modules\Pages\Views\sections\_image', ['image' => ['alt' => ''] + $item['image'], 'imageClass' => 'kp-link-image', 'eager' => $context->isFirst()]) ?></span>
                <?php elseif ($item['icon'] !== ''): ?>
                    <span class="kp-link-media kp-link-icon"><i class="bi <?= esc((string) $item['icon'], 'attr') ?>" aria-hidden="true"></i></span>
                <?php endif ?>
                <span class="kp-link-label"><?= esc((string) $item['label']) ?></span>
            </<?= $tag ?>>
        </li>
    <?php endforeach ?>
</ul>
