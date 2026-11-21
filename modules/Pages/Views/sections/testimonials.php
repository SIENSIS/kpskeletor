<?php
/**
 * Section `pages.testimonials`: quotes with a photo, a name and a role in 1–3 columns.
 * Override: `themes/<slug>/Views/overrides/Pages/sections/testimonials.php`.
 *
 * @var array<string, mixed> $values heading, intro, items (photo, quote, name, role), columns
 */
$columns = max(1, min(3, (int) $values['columns']));
?>
<?= theme_view('Modules\Pages\Views\sections\_heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => true]) ?>
<div class="row row-cols-1<?= $columns > 1 ? ' row-cols-md-2' : '' ?><?= $columns > 2 ? ' row-cols-lg-3' : '' ?> g-4 justify-content-center">
    <?php foreach ($values['items'] as $item): ?>
        <div class="col">
            <figure class="kp-testimonial h-100 mb-0 p-4 rounded-3">
                <i class="bi bi-quote kp-testimonial-mark" aria-hidden="true"></i>
                <blockquote class="kp-testimonial-quote mb-3">
                    <p class="mb-0 kp-pre-line"><?= esc((string) $item['quote']) ?></p>
                </blockquote>
                <figcaption class="d-flex align-items-center gap-3">
                    <?php if (is_array($item['photo'])): ?>
                        <?= theme_view('Modules\Pages\Views\sections\_image', ['image' => $item['photo'], 'imageClass' => 'kp-testimonial-photo rounded-circle flex-shrink-0', 'eager' => false]) ?>
                    <?php endif ?>
                    <span>
                        <span class="d-block fw-semibold"><?= esc((string) $item['name']) ?></span>
                        <?php if ((string) $item['role'] !== ''): ?>
                            <span class="d-block small kp-testimonial-role"><?= esc((string) $item['role']) ?></span>
                        <?php endif ?>
                    </span>
                </figcaption>
            </figure>
        </div>
    <?php endforeach ?>
</div>
