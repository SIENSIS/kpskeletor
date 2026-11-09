<?php
/**
 * Reader screen: filters (type, category, unread) and the posts the user may read as cards.
 *
 * @var string                                               $layout
 * @var list<array<string, mixed>>                           $items
 * @var array{type: string, category: int, unread: bool}     $filters
 * @var list<array{id: int, slug: string, name: string}>     $categories
 * @var string                                               $pager
 * @var int                                                  $total
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form method="get" action="<?= esc(site_url('backend/news'), 'attr') ?>" class="row g-2 align-items-end mb-3 news-filters" role="search">
    <div class="col-12 col-sm-4 col-lg-3">
        <label class="form-label small mb-1" for="news-type"><?= esc(lang('News.fields.type')) ?></label>
        <select class="form-select form-select-sm" id="news-type" name="type">
            <option value=""><?= esc(lang('News.all')) ?></option>
            <?php foreach (['article', 'notice'] as $type): ?>
                <option value="<?= esc($type, 'attr') ?>"<?= $filters['type'] === $type ? ' selected' : '' ?>><?= esc(lang('News.typesPlural.' . $type)) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-3">
        <label class="form-label small mb-1" for="news-category"><?= esc(lang('News.fields.category_id')) ?></label>
        <select class="form-select form-select-sm" id="news-category" name="category">
            <option value="0"><?= esc(lang('News.all')) ?></option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"<?= $filters['category'] === $category['id'] ? ' selected' : '' ?>><?= esc($category['name']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-12 col-sm-4 col-lg-3">
        <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" id="news-unread" name="unread" value="1"<?= $filters['unread'] ? ' checked' : '' ?>>
            <label class="form-check-label small" for="news-unread"><?= esc(lang('News.unreadOnly')) ?></label>
        </div>
    </div>
    <div class="col-12 col-lg-3 text-lg-end">
        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel" aria-hidden="true"></i> <?= esc(lang('News.filter')) ?></button>
    </div>
</form>

<?php if ($items === []): ?>
    <?= theme_component('empty_state', ['icon' => 'bi bi-newspaper', 'title' => lang('News.readerEmpty'), 'message' => lang('News.readerEmptyHelp'), 'action' => null, 'compact' => false]) ?>
<?php else: ?>
    <p class="small text-body-secondary"><?= esc(lang('News.results', [$total])) ?></p>
    <div class="row g-3 news-cards">
        <?php foreach ($items as $item): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card h-100 shadow-sm news-card<?= $item['read'] ? ' is-read' : '' ?>">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex flex-wrap align-items-center gap-1 mb-2 small">
                            <?php if ($item['type'] === 'notice'): ?>
                                <?= theme_view('Modules\News\Views\partials\priority', ['priority' => $item['priority']]) ?>
                            <?php else: ?>
                                <?= ui_badge(lang('News.types.article'), 'secondary', 'bi bi-file-text') ?>
                            <?php endif ?>
                            <?php if ($item['pinned']): ?>
                                <i class="bi bi-pin-angle-fill text-body-secondary" title="<?= esc(lang('News.pinned'), 'attr') ?>" aria-label="<?= esc(lang('News.pinned'), 'attr') ?>"></i>
                            <?php endif ?>
                            <?php if ($item['category'] !== null): ?>
                                <span class="text-body-secondary"><?= esc($item['category']) ?></span>
                            <?php endif ?>
                        </div>
                        <h2 class="h6 card-title"><a class="text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a></h2>
                        <?php if ($item['summary'] !== ''): ?>
                            <p class="card-text small text-body-secondary"><?= esc($item['summary']) ?></p>
                        <?php endif ?>
                        <div class="mt-auto d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                            <span class="text-body-secondary"><?= ui_date($item['date'], 'date') ?></span>
                            <?php if ($item['read']): ?>
                                <span class="text-success"><i class="bi bi-check2-all" aria-hidden="true"></i> <?= esc(lang('News.read')) ?></span>
                            <?php else: ?>
                                <span class="badge text-bg-primary"><?= esc(lang('News.unread')) ?></span>
                            <?php endif ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach ?>
    </div>
    <div class="mt-3"><?= $pager ?></div>
<?php endif ?>
<?= $this->endSection() ?>
