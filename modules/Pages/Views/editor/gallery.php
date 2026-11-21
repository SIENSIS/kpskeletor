<?php
/**
 * "Add section": the gallery of the section types of the enabled modules, with their thumbnails
 * and descriptions.
 *
 * @var string                                          $layout
 * @var array<string, \Modules\Core\Sections\SectionType> $types
 * @var string                                          $newUrl
 * @var string                                          $backUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-body-secondary"><?= esc(lang('Pages.sections.galleryHelp')) ?></p>
<ul class="list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xxl-4 g-3 kp-section-gallery">
    <?php foreach ($types as $key => $type): ?>
        <li class="col">
            <article class="card h-100 shadow-sm kp-section-type">
                <?php if ($type->thumbnail() !== null): ?>
                    <img class="card-img-top kp-section-thumb" src="<?= esc((string) $type->thumbnail(), 'attr') ?>" alt="" width="320" height="180" loading="lazy">
                <?php endif ?>
                <div class="card-body d-flex flex-column">
                    <h2 class="h6 card-title"><i class="<?= esc($type->icon(), 'attr') ?> me-1" aria-hidden="true"></i><?= esc($type->label()) ?></h2>
                    <p class="card-text small text-body-secondary flex-grow-1"><?= esc($type->description()) ?></p>
                    <a class="btn btn-sm btn-primary stretched-link align-self-start" href="<?= esc($newUrl . '/' . rawurlencode($key), 'attr') ?>" aria-label="<?= esc(lang('Pages.sections.addType', [$type->label()]), 'attr') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.choose')) ?></a>
                </div>
            </article>
        </li>
    <?php endforeach ?>
</ul>
<p class="mt-3"><a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Pages.sections.back')) ?></a></p>
<?= $this->endSection() ?>
