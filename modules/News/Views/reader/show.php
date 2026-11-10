<?php
/**
 * Reader screen: one post (sanitised body), its read state and "mark as read".
 *
 * @var string               $layout
 * @var array<string, mixed> $post
 * @var string|null          $cover
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<article class="card shadow-sm news-post">
    <?php if ($cover !== null): ?>
        <img class="card-img-top news-cover" src="<?= esc($cover, 'attr') ?>" alt="">
    <?php endif ?>
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 small text-body-secondary">
            <?php if ($post['type'] === 'notice'): ?>
                <?= theme_view('Modules\News\Views\partials\priority', ['priority' => $post['priority']]) ?>
            <?php endif ?>
            <?php if ($post['pinned']): ?>
                <span><i class="bi bi-pin-angle-fill" aria-hidden="true"></i> <?= esc(lang('News.pinned')) ?></span>
            <?php endif ?>
            <span><i class="bi bi-calendar3" aria-hidden="true"></i> <?= ui_date($post['date'], 'datetime') ?></span>
            <?php if ($post['category'] !== null): ?>
                <span><i class="bi bi-tag" aria-hidden="true"></i> <?= esc($post['category']) ?></span>
            <?php endif ?>
            <?php if ($post['expires'] !== null): ?>
                <span><i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= esc(lang('News.expiresOn')) ?> <?= ui_date($post['expires'], 'datetime') ?></span>
            <?php endif ?>
        </div>
        <?php if ($post['summary'] !== ''): ?>
            <p class="lead"><?= esc($post['summary']) ?></p>
        <?php endif ?>
        <div class="news-body text-break">
            <?php /* Sanitised with HTMLPurifier when it was saved (Core HtmlSanitizer). */ ?>
            <?= $post['body'] ?>
        </div>
    </div>
    <div class="card-footer bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= esc(site_url('backend/news'), 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('News.backToNews')) ?></a>
        <?php if ($post['read']): ?>
            <span class="text-success small"><i class="bi bi-check2-all" aria-hidden="true"></i> <?= esc(lang('News.read')) ?></span>
        <?php else: ?>
            <form method="post" action="<?= esc($post['markUrl'], 'attr') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2" aria-hidden="true"></i> <?= esc(lang('News.markRead')) ?></button>
            </form>
        <?php endif ?>
    </div>
</article>
<?= $this->endSection() ?>
