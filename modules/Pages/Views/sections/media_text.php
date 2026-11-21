<?php
/**
 * Section `pages.media_text`: an image on one side, a title, rich text and a button on the other
 * (stacked on phones, the image first).
 * Override: `themes/<slug>/Views/overrides/Pages/sections/media_text.php`.
 *
 * @var array<string, mixed>                 $values image, image_side, title, text, link, button_style
 * @var \Modules\Core\Sections\RenderContext $context
 */
$end = $values['image_side'] === 'end';
?>
<div class="row align-items-center g-4 g-lg-5 kp-media-text">
    <div class="col-md-6<?= $end ? ' order-md-2' : '' ?>">
        <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $values['image'], 'imageClass' => 'img-fluid rounded-3 shadow-sm kp-media-image', 'eager' => $context->isFirst()]) ?>
    </div>
    <div class="col-md-6<?= $end ? ' order-md-1' : '' ?>">
        <?php if ($values['title'] !== ''): ?>
            <h2 class="kp-section-title text-break"><?= esc((string) $values['title']) ?></h2>
        <?php endif ?>
        <?php if ($values['text'] !== ''): ?>
            <div class="kp-rich-text text-break"><?= $values['text'] ?></div>
        <?php endif ?>
        <?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $values['link'], 'buttonStyle' => $values['button_style'], 'buttonClass' => 'mt-2']) ?>
    </div>
</div>
