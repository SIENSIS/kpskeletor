<?php
/**
 * Section `pages.cards`: heading, introduction and cards (image, title, text, button) in 2–4
 * columns (one on phones, two on tablets).
 * Override: `themes/<slug>/Views/overrides/Pages/sections/cards.php`.
 *
 * @var array<string, mixed>                 $values heading, intro, cards (image, title, text, link), columns, button_style
 * @var \Modules\Core\Sections\RenderContext $context
 */
$columns = max(2, min(4, (int) $values['columns']));
?>
<?= theme_view('Modules\Pages\Views\sections\_heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => true]) ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-<?= $columns ?> g-4">
    <?php foreach ($values['cards'] as $card): ?>
        <div class="col">
            <article class="card kp-card h-100 shadow-sm">
                <?php if (is_array($card['image'])): ?>
                    <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $card['image'], 'imageClass' => 'card-img-top kp-card-image', 'eager' => false]) ?>
                <?php endif ?>
                <div class="card-body d-flex flex-column">
                    <h3 class="card-title h5 text-break"><?= esc((string) $card['title']) ?></h3>
                    <?php if ($card['text'] !== ''): ?>
                        <p class="card-text kp-pre-line"><?= esc((string) $card['text']) ?></p>
                    <?php endif ?>
                    <?php if (is_array($card['link'])): ?>
                        <div class="mt-auto pt-2"><?= theme_view('Modules\Pages\Views\sections\_button', ['link' => $card['link'], 'buttonStyle' => $values['button_style'], 'buttonClass' => 'btn-sm']) ?></div>
                    <?php endif ?>
                </div>
            </article>
        </div>
    <?php endforeach ?>
</div>
