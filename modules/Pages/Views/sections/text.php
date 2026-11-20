<?php
/**
 * Section `pages.text`: an optional heading and rich text (sanitised when saved and again here), in
 * a readable column or the whole container.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/text.php`.
 *
 * @var array<string, mixed>                 $values heading, text, column
 * @var \Modules\Core\Sections\RenderContext $context
 */
?>
<div class="kp-text<?= $values['column'] === 'narrow' ? ' page-column mx-auto' : '' ?>">
    <?php if ($values['heading'] !== ''): ?>
        <h2 class="kp-section-title text-break"><?= esc((string) $values['heading']) ?></h2>
    <?php endif ?>
    <div class="kp-rich-text page-body text-break"><?= $values['text'] ?></div>
</div>
