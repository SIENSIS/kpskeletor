<?php
/**
 * Section `pages.logos`: a strip of logos (alternative text required), each with an optional
 * link; grey until hovered or focused when `grayscale` is on.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/logos.php`.
 *
 * @var array<string, mixed> $values heading, intro, logos (image, link), grayscale
 */
?>
<?= theme_view('Modules\Pages\Views\sections\_heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => true]) ?>
<ul class="kp-logos list-unstyled d-flex flex-wrap justify-content-center align-items-center gap-4 mb-0<?= $values['grayscale'] === true ? ' kp-logos-gray' : '' ?>">
    <?php foreach ($values['logos'] as $logo): ?>
        <?php if (! is_array($logo['image'])) {
            continue;
        } ?>
        <li class="kp-logo">
            <?php if (is_array($logo['link'])): ?>
                <a class="kp-logo-link d-inline-block" href="<?= esc((string) $logo['link']['href'], 'attr') ?>"<?= $logo['link']['external'] ? ' rel="noopener"' : '' ?>>
                    <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $logo['image'], 'imageClass' => 'kp-logo-image', 'eager' => false]) ?>
                    <?php if ($logo['link']['external']): ?><span class="visually-hidden"> <?= esc(lang('Pages.sectionTypes.externalLink')) ?></span><?php endif ?>
                </a>
            <?php else: ?>
                <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $logo['image'], 'imageClass' => 'kp-logo-image', 'eager' => false]) ?>
            <?php endif ?>
        </li>
    <?php endforeach ?>
</ul>
