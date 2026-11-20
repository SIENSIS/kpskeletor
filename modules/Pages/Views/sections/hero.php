<?php
/**
 * Section `pages.hero` (hero banner). Override: `themes/<slug>/Views/overrides/Pages/sections/hero.php`.
 *
 * @var array<string, mixed>                  $values  resolved values (title, text, primary, primary_style, secondary, secondary_style, background, overlay, side_image, height, align, edge)
 * @var string                                $style
 * @var \Modules\Core\Sections\RenderContext  $context
 */
$background = $values['background'];
$side       = $values['side_image'];
$center     = $values['align'] === 'center';
?>
<div class="kp-hero kp-hero-<?= esc((string) $values['height'], 'attr') ?> kp-hero-edge-<?= esc((string) $values['edge'], 'attr') ?><?= is_array($background) ? ' kp-hero-has-image kp-overlay-' . esc((string) $values['overlay'], 'attr') : '' ?>"<?= is_array($background) && $values['overlay'] === 'light' ? ' data-bs-theme="light"' : '' ?>>
    <?php if (is_array($background)): ?>
        <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $background, 'imageClass' => 'kp-hero-bg', 'eager' => $context->isFirst()]) ?>
        <span class="kp-hero-overlay" aria-hidden="true"></span>
    <?php endif ?>
    <div class="kp-hero-body row align-items-center g-4<?= $center && ! is_array($side) ? ' justify-content-center text-center' : '' ?>">
        <div class="<?= is_array($side) ? 'col-md-7' : 'col-lg-9' ?> kp-hero-text">
            <h2 class="kp-hero-title display-5 fw-bold text-break"><?= esc((string) $values['title']) ?></h2>
            <?php if ($values['text'] !== ''): ?>
                <p class="lead mb-0 kp-pre-line"><?= esc((string) $values['text']) ?></p>
            <?php endif ?>
            <?php if (is_array($values['primary']) || is_array($values['secondary'])): ?>
                <div class="d-flex flex-wrap gap-2 mt-4<?= $center && ! is_array($side) ? ' justify-content-center' : '' ?>">
                    <?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $values['primary'], 'buttonStyle' => $values['primary_style'], 'buttonClass' => 'btn-lg']) ?>
                    <?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $values['secondary'], 'buttonStyle' => $values['secondary_style'], 'buttonClass' => 'btn-lg']) ?>
                </div>
            <?php endif ?>
        </div>
        <?php if (is_array($side)): ?>
            <div class="col-md-5 kp-hero-side text-center">
                <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $side, 'imageClass' => 'img-fluid kp-hero-side-image', 'eager' => $context->isFirst()]) ?>
            </div>
        <?php endif ?>
    </div>
</div>
<?php if ($values['edge'] === 'wave'): ?>
    <span class="kp-edge-wave" aria-hidden="true"></span>
<?php endif ?>
