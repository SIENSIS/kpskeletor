<?php
/**
 * Widget "Latest news" (`Modules\News\Widgets\LatestNews`).
 *
 * @var list<array{title: string, category: string|null, date: string, url: string}> $items
 * @var string                                                                       $url
 */
?>
<ul class="list-group list-group-flush mb-2">
    <?php foreach ($items as $item): ?>
        <li class="list-group-item px-0">
            <a class="fw-semibold text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a>
            <div class="small text-body-secondary">
                <?= ui_date($item['date'], 'date') ?><?php if ($item['category'] !== null): ?> · <?= esc($item['category']) ?><?php endif ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('News.widgets.latest.all')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
