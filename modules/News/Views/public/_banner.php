<?php
/**
 * The banner of a category as a hero (category page and its articles): a decorative image under
 * a dark layer, with the category's name. The page's `<h1>` stays in the page.
 *
 * @var string $bannerUrl
 * @var string $label     the category's name
 * @var bool   $heading   whether the name is the page's title (`<h1>`), else a paragraph
 * @var string $link      the category's page ('' for none)
 */
?>
<div class="news-banner kp-hero kp-hero-s kp-hero-has-image kp-overlay-dark rounded-3 overflow-hidden mb-4" data-bs-theme="dark">
    <img class="kp-hero-bg" src="<?= esc($bannerUrl, 'attr') ?>" alt="" decoding="async">
    <span class="kp-hero-overlay" aria-hidden="true"></span>
    <div class="kp-hero-body px-4">
        <?php if ($heading): ?>
            <h1 class="display-6 fw-bold text-break mb-0"><?= esc($label) ?></h1>
        <?php elseif ($link !== ''): ?>
            <p class="h4 fw-bold mb-0"><a class="link-light text-decoration-none" href="<?= esc($link, 'attr') ?>"><?= esc($label) ?></a></p>
        <?php else: ?>
            <p class="h4 fw-bold mb-0"><?= esc($label) ?></p>
        <?php endif ?>
    </div>
</div>
