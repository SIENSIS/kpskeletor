<?php
/**
 * Widget "Notices" (`Modules\News\Widgets\Notices`): priority, pinned mark, read state and a
 * "mark as read" form (POST, CSRF) per notice.
 *
 * @var list<array{title: string, summary: string, priority: string, pinned: bool, date: string, read: bool, url: string, markUrl: string}> $items
 * @var string                                                                                                                           $url
 */
?>
<ul class="list-group list-group-flush mb-2 news-notices">
    <?php foreach ($items as $item): ?>
        <li class="list-group-item px-0 news-notice news-notice-<?= esc($item['priority'], 'attr') ?><?= $item['read'] ? ' is-read' : '' ?>">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                        <?= theme_view('Modules\News\Views\partials\priority', ['priority' => $item['priority']]) ?>
                        <?php if ($item['pinned']): ?>
                            <i class="bi bi-pin-angle-fill text-body-secondary" title="<?= esc(lang('News.pinned'), 'attr') ?>" aria-label="<?= esc(lang('News.pinned'), 'attr') ?>"></i>
                        <?php endif ?>
                        <a class="fw-semibold text-break" href="<?= esc($item['url'], 'attr') ?>"><?= esc($item['title']) ?></a>
                    </div>
                    <?php if ($item['summary'] !== ''): ?>
                        <p class="small mb-1 text-body-secondary"><?= esc($item['summary']) ?></p>
                    <?php endif ?>
                    <div class="small text-body-secondary"><?= ui_date($item['date'], 'relative') ?></div>
                </div>
                <?php if ($item['read']): ?>
                    <span class="small text-success text-nowrap"><i class="bi bi-check2-all" aria-hidden="true"></i> <?= esc(lang('News.read')) ?></span>
                <?php else: ?>
                    <form method="post" action="<?= esc($item['markUrl'], 'attr') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return" value="backend">
                        <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap">
                            <i class="bi bi-check2" aria-hidden="true"></i> <?= esc(lang('News.markRead')) ?>
                        </button>
                    </form>
                <?php endif ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('News.widgets.notices.all')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
