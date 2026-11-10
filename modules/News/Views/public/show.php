<?php
/**
 * Public page of a news item (public posts only). The body was sanitised with HTMLPurifier.
 *
 * @var string               $layout
 * @var array<string, mixed> $post
 * @var string               $body
 * @var string|null          $cover
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<article class="news-article mx-auto">
    <header class="mb-4">
        <h1 class="display-6 text-break"><?= esc((string) $post['title']) ?></h1>
        <p class="text-body-secondary small mb-0">
            <i class="bi bi-calendar3" aria-hidden="true"></i> <?= ui_date((string) $post['published_at'], 'date') ?>
            <?php if ($post['category_slug'] !== null): ?>
                · <a href="<?= esc(site_url('news/category/' . rawurlencode((string) $post['category_slug'])), 'attr') ?>"><?= esc((string) $post['category']) ?></a>
            <?php endif ?>
        </p>
    </header>
    <?php if ($cover !== null): ?>
        <img class="img-fluid rounded mb-4 news-cover" src="<?= esc($cover, 'attr') ?>" alt="">
    <?php endif ?>
    <?php if ((string) $post['summary'] !== ''): ?>
        <p class="lead"><?= esc((string) $post['summary']) ?></p>
    <?php endif ?>
    <div class="news-body text-break"><?= $body ?></div>
    <p class="mt-4"><a href="<?= esc(site_url('news'), 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('News.backToNews')) ?></a></p>
</article>
<?= $this->endSection() ?>
