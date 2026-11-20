<?php
/**
 * Section `pages.cta` (call to action): a centred title, text and button on the section's
 * background. Override: `themes/<slug>/Views/overrides/Pages/sections/cta.php`.
 *
 * @var array<string, mixed>                 $values title, text, link, button_style
 * @var \Modules\Core\Sections\RenderContext $context
 */
?>
<div class="kp-cta text-center mx-auto">
    <h2 class="kp-section-title display-6 fw-bold text-break"><?= esc((string) $values['title']) ?></h2>
    <?php if ($values['text'] !== ''): ?>
        <p class="lead kp-pre-line"><?= esc((string) $values['text']) ?></p>
    <?php endif ?>
    <?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $values['link'], 'buttonStyle' => $values['button_style'], 'buttonClass' => 'btn-lg mt-2']) ?>
</div>
