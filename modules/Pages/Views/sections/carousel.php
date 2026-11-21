<?php
/**
 * Section `pages.carousel` (D-46-02): slides like the hero banner. `pages/carousel` shows one
 * slide at a time with arrows, dots and keyboard; rotation only when turned on, with a pause
 * button, stopping on hover and focus and never with `prefers-reduced-motion`. Without
 * JavaScript the slides are shown one under the other.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/carousel.php`.
 *
 * @var array<string, mixed>                 $values  label, slides (image, title, text, link), overlay, height, button_style, rotate, interval
 * @var \Modules\Core\Sections\SectionData   $section
 * @var \Modules\Core\Sections\RenderContext $context
 */
$slides   = array_values(array_filter($values['slides'], static fn (array $slide): bool => is_array($slide['image'])));
$count    = count($slides);
$id       = 'kp-carousel-' . (int) ($section->id ?? 0);
$rotate   = $values['rotate'] === true && $count > 1;
$interval = max(\Modules\Pages\Sections\CarouselSection::MIN_INTERVAL, (int) ($values['interval'] ?? 8));
$overlay  = in_array($values['overlay'], ['dark', 'primary', 'light'], true) ? (string) $values['overlay'] : 'dark';
?>
<?php if ($count > 0): ?>
<div class="kp-carousel" id="<?= esc($id, 'attr') ?>" role="region" aria-roledescription="<?= esc(lang('Pages.carousel.roledescription'), 'attr') ?>" aria-label="<?= esc((string) $values['label'], 'attr') ?>"
     data-component="pages/carousel" data-rotate="<?= $rotate ? '1' : '0' ?>" data-interval="<?= $interval ?>"
     data-text-slide="<?= esc(lang('Pages.carousel.slide'), 'attr') ?>" data-text-pause="<?= esc(lang('Pages.carousel.pause'), 'attr') ?>" data-text-play="<?= esc(lang('Pages.carousel.play'), 'attr') ?>">
    <div class="kp-carousel-slides" aria-live="<?= $rotate ? 'off' : 'polite' ?>" data-carousel-slides>
        <?php foreach ($slides as $i => $slide): ?>
            <div class="kp-carousel-slide kp-hero kp-hero-<?= esc((string) $values['height'], 'attr') ?> kp-hero-has-image kp-overlay-<?= esc($overlay, 'attr') ?>" role="group" aria-roledescription="<?= esc(lang('Pages.carousel.slideRole'), 'attr') ?>" aria-label="<?= esc(lang('Pages.carousel.slide', [$i + 1, $count]), 'attr') ?>" id="<?= esc($id . '-' . ($i + 1), 'attr') ?>" data-carousel-slide<?= $overlay === 'light' ? ' data-bs-theme="light"' : '' ?>>
                <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $slide['image'], 'imageClass' => 'kp-hero-bg', 'eager' => $context->isFirst() && $i === 0]) ?>
                <span class="kp-hero-overlay" aria-hidden="true"></span>
                <?php if ((string) $slide['title'] !== '' || (string) $slide['text'] !== '' || is_array($slide['link'])): ?>
                    <div class="kp-hero-body container">
                        <div class="kp-hero-text col-lg-8">
                            <?php if ((string) $slide['title'] !== ''): ?>
                                <h2 class="display-6 fw-bold text-break"><?= esc((string) $slide['title']) ?></h2>
                            <?php endif ?>
                            <?php if ((string) $slide['text'] !== ''): ?>
                                <p class="lead kp-pre-line"><?= esc((string) $slide['text']) ?></p>
                            <?php endif ?>
                            <?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $slide['link'], 'buttonStyle' => $values['button_style'], 'buttonClass' => '']) ?>
                        </div>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>
    <?php if ($count > 1): ?>
        <div class="kp-carousel-controls" data-carousel-controls hidden>
            <?php if ($rotate): ?>
                <button type="button" class="btn btn-light btn-sm kp-carousel-pause" data-carousel-pause aria-label="<?= esc(lang('Pages.carousel.pause'), 'attr') ?>"><i class="bi bi-pause-fill" aria-hidden="true"></i></button>
            <?php endif ?>
            <button type="button" class="btn btn-light btn-sm kp-carousel-prev" data-carousel-prev aria-controls="<?= esc($id, 'attr') ?>" aria-label="<?= esc(lang('Pages.carousel.previous'), 'attr') ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <div class="kp-carousel-dots" role="group" aria-label="<?= esc(lang('Pages.carousel.choose'), 'attr') ?>">
                <?php for ($i = 1; $i <= $count; $i++): ?>
                    <button type="button" class="kp-carousel-dot" data-carousel-dot="<?= $i - 1 ?>" aria-controls="<?= esc($id . '-' . $i, 'attr') ?>" aria-label="<?= esc(lang('Pages.carousel.slide', [$i, $count]), 'attr') ?>" aria-pressed="<?= $i === 1 ? 'true' : 'false' ?>"></button>
                <?php endfor ?>
            </div>
            <button type="button" class="btn btn-light btn-sm kp-carousel-next" data-carousel-next aria-controls="<?= esc($id, 'attr') ?>" aria-label="<?= esc(lang('Pages.carousel.next'), 'attr') ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
    <?php endif ?>
</div>
<?php endif ?>
