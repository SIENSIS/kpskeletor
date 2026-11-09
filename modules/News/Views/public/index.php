<?php
/**
 * Public list of news (public posts only), with the categories and the RSS link.
 *
 * @var string                                           $layout
 * @var string                                           $title
 * @var list<array<string, mixed>>                       $items
 * @var list<array{id: int, slug: string, name: string}> $categories
 * @var string|null                                      $current
 * @var string                                           $pager
 * @var string                                           $feedUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h2 mb-0"><?= esc($title) ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?= esc($feedUrl, 'attr') ?>"><i class="bi bi-rss" aria-hidden="true"></i> <?= esc(lang('News.rss')) ?></a>
</div>
<?php if ($categories !== []): ?>
    <nav class="mb-4" aria-label="<?= esc(lang('News.categories'), 'attr') ?>">
        <ul class="nav nav-pills small gap-1 flex-wrap">
            <li class="nav-item"><a class="nav-link<?= $current === null ? ' active' : '' ?>" href="<?= esc(site_url('news'), 'attr') ?>"<?= $current === null ? ' aria-current="page"' : '' ?>><?= esc(lang('News.all')) ?></a></li>
            <?php foreach ($categories as $category): ?>
                <li class="nav-item"><a class="nav-link<?= $current === $category['slug'] ? ' active' : '' ?>" href="<?= esc(site_url('news/category/' . rawurlencode($category['slug'])), 'attr') ?>"<?= $current === $category['slug'] ? ' aria-current="page"' : '' ?>><?= esc($category['name']) ?></a></li>
            <?php endforeach ?>
        </ul>
    </nav>
<?php endif ?>
<?php if ($items === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-newspaper', 'title' => lang('News.publicEmpty'), 'message' => null, 'action' => null, 'compact' => false]) ?>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($items as $item): ?>
            <?php $cover = \Modules\News\Storage\CoverUploads::urlOf(is_string($item['cover']) ? $item['cover'] : null); ?>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="card h-100 shadow-sm news-card">
                    <?php if ($cover !== null): ?>
                        <img class="card-img-top news-card-cover" src="<?= esc($cover, 'attr') ?>" alt="" loading="lazy">
                    <?php endif ?>
                    <div class="card-body d-flex flex-column">
                        <div class="small text-body-secondary mb-1">
                            <?= ui_date((string) $item['published_at'], 'date') ?>
                            <?php if ($item['category'] !== null): ?> · <?= esc((string) $item['category']) ?><?php endif ?>
                        </div>
                        <h2 class="h5 card-title"><a class="text-break" href="<?= esc(site_url('news/' . rawurlencode((string) $item['slug'])), 'attr') ?>"><?= esc((string) $item['title']) ?></a></h2>
                        <?php if ((string) $item['summary'] !== ''): ?>
                            <p class="card-text text-body-secondary"><?= esc((string) $item['summary']) ?></p>
                        <?php endif ?>
                    </div>
                </article>
            </div>
        <?php endforeach ?>
    </div>
    <div class="mt-4"><?= $pager ?></div>
<?php endif ?>
<?= $this->endSection() ?>
