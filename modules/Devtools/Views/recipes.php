<?php
/**
 * `backend/devtools/recipes`: the CRUD recipes (Modules\Devtools\Recipes\RecipeBook). The texts
 * are `Devtools.recipes.<id>.title|what|when`; the code is shown escaped, never run.
 *
 * @var string                                                                                       $layout
 * @var list<array{id: string, icon: string, source: string, doc: string, anchor: string, code: string}> $recipes
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<p class="text-body-secondary"><?= esc(lang('Devtools.recipes.intro')) ?></p>
<nav class="mb-4" aria-label="<?= esc(lang('Devtools.recipes.contents'), 'attr') ?>">
    <ul class="nav nav-pills flex-wrap gap-1 small">
        <?php foreach ($recipes as $recipe): ?>
            <li class="nav-item"><a class="nav-link py-1 px-2" href="#recipe-<?= esc($recipe['id'], 'attr') ?>"><?= esc(lang('Devtools.recipes.' . $recipe['id'] . '.title')) ?></a></li>
        <?php endforeach ?>
    </ul>
</nav>
<div class="row g-3">
    <?php foreach ($recipes as $recipe): ?>
        <?php $key = 'Devtools.recipes.' . $recipe['id']; ?>
        <div class="col-12 col-xxl-6">
            <section class="card h-100" id="recipe-<?= esc($recipe['id'], 'attr') ?>" data-recipe="<?= esc($recipe['id'], 'attr') ?>">
                <div class="card-header d-flex align-items-center gap-2">
                    <i class="<?= esc($recipe['icon'], 'attr') ?>" aria-hidden="true"></i>
                    <h2 class="h6 mb-0 flex-grow-1"><?= esc(lang($key . '.title')) ?></h2>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-component="devtools/copy" data-copy-target="recipe-code-<?= esc($recipe['id'], 'attr') ?>" data-options="<?= esc(json_encode(['copied' => lang('Devtools.recipes.copied')], JSON_THROW_ON_ERROR), 'attr') ?>">
                        <i class="bi bi-clipboard" aria-hidden="true"></i> <?= esc(lang('Devtools.recipes.copy')) ?>
                    </button>
                </div>
                <div class="card-body">
                    <dl class="mb-3">
                        <dt class="small text-body-secondary"><?= esc(lang('Devtools.recipes.what')) ?></dt>
                        <dd><?= esc(lang($key . '.what')) ?></dd>
                        <dt class="small text-body-secondary"><?= esc(lang('Devtools.recipes.when')) ?></dt>
                        <dd class="mb-0"><?= esc(lang($key . '.when')) ?></dd>
                    </dl>
                    <pre class="bg-body-tertiary border rounded p-2 small mb-0"><code id="recipe-code-<?= esc($recipe['id'], 'attr') ?>"><?= esc($recipe['code']) ?></code></pre>
                </div>
                <div class="card-footer small d-flex flex-wrap gap-3">
                    <span><i class="bi bi-file-earmark-code me-1" aria-hidden="true"></i><?= esc(lang('Devtools.recipes.source')) ?>: <code><?= esc($recipe['source']) ?></code></span>
                    <a href="<?= esc(backend_url('devtools/docs/' . $recipe['doc']) . '#' . $recipe['anchor'], 'attr') ?>"><i class="bi bi-book me-1" aria-hidden="true"></i>docs/<?= esc($recipe['doc']) ?>.md#<?= esc($recipe['anchor']) ?></a>
                </div>
            </section>
        </div>
    <?php endforeach ?>
</div>
<p class="small text-body-secondary mt-4"><?= esc(lang('Devtools.recipes.more')) ?></p>
<?= $this->endSection() ?>
