<?php
/**
 * "New page from a model": the gallery of page models (thumbnail, name, description); choosing
 * one creates a draft page in `sections` layout with placeholder texts and images.
 *
 * @var string                                          $layout
 * @var array<string, \Modules\Core\Sections\PageModel> $models
 * @var string                                          $action
 * @var string                                          $backUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-body-secondary"><?= esc(lang('Pages.models.help')) ?></p>
<?php if ($models === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-file-earmark-plus', 'title' => lang('Pages.models.none'), 'message' => null]) ?>
<?php else: ?>
    <ul class="list-unstyled row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-3 kp-section-gallery">
        <?php foreach ($models as $key => $model): ?>
            <li class="col">
                <article class="card h-100 shadow-sm kp-section-type">
                    <?php if ($model->thumbnail() !== null): ?>
                        <img class="card-img-top kp-section-thumb" src="<?= esc((string) $model->thumbnail(), 'attr') ?>" alt="" width="320" height="240" loading="lazy">
                    <?php endif ?>
                    <div class="card-body d-flex flex-column">
                        <h2 class="h6 card-title"><i class="<?= esc($model->icon(), 'attr') ?> me-1" aria-hidden="true"></i><?= esc($model->label()) ?></h2>
                        <p class="card-text small text-body-secondary flex-grow-1"><?= esc($model->description()) ?></p>
                        <form method="post" action="<?= esc($action, 'attr') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="model" value="<?= esc($key, 'attr') ?>">
                            <button type="submit" class="btn btn-sm btn-primary" aria-label="<?= esc(lang('Pages.models.use') . ': ' . $model->label(), 'attr') ?>"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Pages.models.use')) ?></button>
                        </form>
                    </div>
                </article>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
<p class="mt-3"><a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= esc(lang('Pages.models.back')) ?></a></p>
<?= $this->endSection() ?>
