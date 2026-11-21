<?php
/**
 * Section `news.list`: the newest public news, or the full list with pages and a search box.
 * Override: `themes/<slug>/Views/overrides/News/sections/list.php`.
 *
 * @var array<string, mixed>                 $values  heading, intro, mode, limit, categories, columns, show_date, show_summary, show_image, search, more
 * @var string                               $style   cards | list
 * @var list<array<string, mixed>>           $items   rows of NewsRepository (public posts only)
 * @var bool                                 $full    full list (pages, search)
 * @var \Modules\Core\Sections\SectionQuery  $query
 * @var string                               $search
 * @var int                                  $page
 * @var int                                  $pages
 * @var \Modules\Core\Sections\RenderContext $context
 */
$columns = max(2, min(4, (int) $values['columns']));
$heading = (string) $values['heading'];
$titleTag = $heading !== '' ? 'h3' : 'h2';
?>
<div id="<?= esc($query->anchor(), 'attr') ?>" class="kp-news-list">
    <?= theme_view('Modules\Core\Views\sections\heading', ['heading' => $heading, 'intro' => (string) $values['intro'], 'centered' => false]) ?>
    <?php if ($full && $values['search'] === true): ?>
        <form method="get" action="<?= esc(current_url() . '#' . $query->anchor(), 'attr') ?>" class="kp-news-search mb-4" role="search">
            <?php foreach ((array) service('request')->getGet() as $name => $value): ?>
                <?php if (is_string($value) && $name !== $query->name('q') && $name !== $query->name('page')): ?>
                    <input type="hidden" name="<?= esc((string) $name, 'attr') ?>" value="<?= esc($value, 'attr') ?>">
                <?php endif ?>
            <?php endforeach ?>
            <div class="input-group">
                <label class="visually-hidden" for="<?= esc($query->name('q'), 'attr') ?>"><?= esc(lang('News.sections.list.searchLabel')) ?></label>
                <input type="search" class="form-control" id="<?= esc($query->name('q'), 'attr') ?>" name="<?= esc($query->name('q'), 'attr') ?>" value="<?= esc($search, 'attr') ?>" maxlength="100" placeholder="<?= esc(lang('News.sections.list.searchPlaceholder'), 'attr') ?>">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i><span class="visually-hidden"> <?= esc(lang('News.sections.list.searchButton')) ?></span></button>
            </div>
        </form>
    <?php endif ?>
    <?php if ($items === []): ?>
        <p class="text-body-secondary mb-0"><?= esc(lang($search !== '' ? 'News.sections.list.noResults' : 'News.publicEmpty')) ?></p>
    <?php elseif ($style === 'list'): ?>
        <ul class="list-unstyled kp-news-rows mb-0">
            <?php foreach ($items as $item): ?>
                <?php $url = site_url('news/' . rawurlencode((string) $item['slug'])); ?>
                <li class="kp-news-row d-flex gap-3 py-3 border-bottom">
                    <?php $cover = $values['show_image'] === true ? \Modules\News\Storage\CoverUploads::urlOf(is_string($item['cover']) ? $item['cover'] : null) : null; ?>
                    <?php if ($cover !== null): ?>
                        <img class="kp-news-row-image rounded flex-shrink-0" src="<?= esc($cover, 'attr') ?>" alt="" loading="lazy" decoding="async">
                    <?php endif ?>
                    <div class="min-w-0">
                        <<?= $titleTag ?> class="h5 mb-1"><a class="text-break" href="<?= esc($url, 'attr') ?>"><?= esc((string) $item['title']) ?></a></<?= $titleTag ?>>
                        <?php if ($values['show_date'] === true || $item['category'] !== null): ?>
                            <div class="small text-body-secondary mb-1">
                                <?php if ($values['show_date'] === true): ?><?= ui_date((string) $item['published_at'], 'date') ?><?php endif ?>
                                <?php if ($item['category'] !== null): ?><?= $values['show_date'] === true ? ' · ' : '' ?><?= esc((string) $item['category']) ?><?php endif ?>
                            </div>
                        <?php endif ?>
                        <?php if ($values['show_summary'] === true && (string) $item['summary'] !== ''): ?>
                            <p class="mb-0 text-body-secondary"><?= esc((string) $item['summary']) ?></p>
                        <?php endif ?>
                    </div>
                </li>
            <?php endforeach ?>
        </ul>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-<?= $columns ?> g-4">
            <?php foreach ($items as $item): ?>
                <?php
                $url   = site_url('news/' . rawurlencode((string) $item['slug']));
                $cover = $values['show_image'] === true ? \Modules\News\Storage\CoverUploads::urlOf(is_string($item['cover']) ? $item['cover'] : null) : null;
                ?>
                <div class="col">
                    <article class="card kp-card h-100 shadow-sm">
                        <?php if ($cover !== null): ?>
                            <img class="card-img-top kp-card-image" src="<?= esc($cover, 'attr') ?>" alt="" loading="lazy" decoding="async">
                        <?php endif ?>
                        <div class="card-body d-flex flex-column">
                            <?php if ($values['show_date'] === true || $item['category'] !== null): ?>
                                <div class="small text-body-secondary mb-1">
                                    <?php if ($values['show_date'] === true): ?><?= ui_date((string) $item['published_at'], 'date') ?><?php endif ?>
                                    <?php if ($item['category'] !== null): ?><?= $values['show_date'] === true ? ' · ' : '' ?><?= esc((string) $item['category']) ?><?php endif ?>
                                </div>
                            <?php endif ?>
                            <<?= $titleTag ?> class="card-title h5"><a class="stretched-link text-break" href="<?= esc($url, 'attr') ?>"><?= esc((string) $item['title']) ?></a></<?= $titleTag ?>>
                            <?php if ($values['show_summary'] === true && (string) $item['summary'] !== ''): ?>
                                <p class="card-text text-body-secondary"><?= esc((string) $item['summary']) ?></p>
                            <?php endif ?>
                        </div>
                    </article>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
    <?php if ($full): ?>
        <?= theme_view('Modules\Core\Views\sections\pager', ['query' => $query, 'page' => $page, 'pages' => $pages, 'label' => lang('News.sections.list.pages', [$heading !== '' ? $heading : lang('News.publicTitle')])]) ?>
    <?php elseif (is_array($values['more'])): ?>
        <p class="mt-4 mb-0 text-center"><a class="btn btn-outline-primary" href="<?= esc((string) $values['more']['href'], 'attr') ?>"><?= esc((string) $values['more']['label'] !== '' ? (string) $values['more']['label'] : lang('News.sections.list.moreDefault')) ?></a></p>
    <?php endif ?>
</div>
