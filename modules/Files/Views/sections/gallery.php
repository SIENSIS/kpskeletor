<?php
/**
 * Section `files.gallery`: the images of a folder as a grid or a masonry; each one opens the
 * lightbox (`files/gallery`: a `<dialog>`, arrows, Escape, captions). Without JavaScript the
 * links open the image itself. Override: `themes/<slug>/Views/overrides/Files/sections/gallery.php`.
 *
 * @var array<string, mixed>                                                                     $values heading, intro, folder, columns, limit, order, captions
 * @var string                                                                                   $style  grid | masonry
 * @var list<array{url: string, width: int|null, height: int|null, caption: string, alt: string}> $images
 * @var \Modules\Core\Sections\SectionData                                                       $section
 */
$columns  = max(2, min(6, (int) $values['columns']));
$captions = $values['captions'] === true;
$label    = (string) $values['heading'] !== '' ? (string) $values['heading'] : lang('Files.sections.gallery.label');
?>
<?= theme_view('Modules\Core\Views\sections\heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => false]) ?>
<div class="kp-gallery kp-gallery-<?= esc($style, 'attr') ?> kp-gallery-cols-<?= $columns ?>" data-component="files/gallery" data-counter="<?= esc(lang('Files.sections.gallery.counter'), 'attr') ?>">
    <ul class="kp-gallery-items list-unstyled mb-0" aria-label="<?= esc($label, 'attr') ?>">
        <?php foreach ($images as $i => $image): ?>
            <li class="kp-gallery-item">
                <a class="kp-gallery-link" href="<?= esc($image['url'], 'attr') ?>" data-gallery-item data-caption="<?= esc($image['caption'], 'attr') ?>" data-alt="<?= esc($image['alt'], 'attr') ?>">
                    <img class="kp-gallery-image" src="<?= esc($image['url'], 'attr') ?>" alt="<?= esc($image['alt'] !== '' ? $image['alt'] : $image['caption'], 'attr') ?>"<?= $image['width'] !== null ? ' width="' . (int) $image['width'] . '" height="' . (int) $image['height'] . '"' : '' ?> loading="lazy" decoding="async">
                </a>
                <?php if ($captions): ?>
                    <p class="kp-gallery-caption small text-body-secondary mb-0 mt-1 text-break"><?= esc($image['caption']) ?></p>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
    <dialog class="kp-lightbox" data-lightbox aria-label="<?= esc($label, 'attr') ?>">
        <div class="kp-lightbox-inner">
            <button type="button" class="btn btn-light kp-lightbox-close" data-lightbox-close aria-label="<?= esc(lang('Files.sections.gallery.close'), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-light kp-lightbox-prev" data-lightbox-prev aria-label="<?= esc(lang('Files.sections.gallery.previous'), 'attr') ?>"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <figure class="kp-lightbox-figure mb-0">
                <img class="kp-lightbox-image" data-lightbox-image src="" alt="">
                <figcaption class="kp-lightbox-caption" data-lightbox-caption aria-live="polite"></figcaption>
            </figure>
            <button type="button" class="btn btn-light kp-lightbox-next" data-lightbox-next aria-label="<?= esc(lang('Files.sections.gallery.next'), 'attr') ?>"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
    </dialog>
</div>
