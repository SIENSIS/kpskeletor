<?php
/**
 * The pages of a list inside a section (`SectionQuery`): previous, numbers around the current
 * page, next. Nothing when there is only one page.
 *
 * @var \Modules\Core\Sections\SectionQuery $query
 * @var int                                 $page
 * @var int                                 $pages
 * @var string                              $label  the navigation's accessible name
 */
if ($pages <= 1) {
    return;
}
$from = max(1, $page - 2);
$to   = min($pages, $page + 2);
?>
<nav class="mt-4" aria-label="<?= esc($label, 'attr') ?>">
    <ul class="pagination justify-content-center flex-wrap mb-0">
        <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
            <?php if ($page > 1): ?>
                <a class="page-link" href="<?= esc($query->url(['page' => $page - 1]), 'attr') ?>" rel="prev"><span aria-hidden="true">&laquo;</span><span class="visually-hidden"><?= esc(lang('Sections.pager.previous')) ?></span></a>
            <?php else: ?>
                <span class="page-link"><span aria-hidden="true">&laquo;</span><span class="visually-hidden"><?= esc(lang('Sections.pager.previous')) ?></span></span>
            <?php endif ?>
        </li>
        <?php for ($i = $from; $i <= $to; $i++): ?>
            <li class="page-item<?= $i === $page ? ' active' : '' ?>">
                <?php if ($i === $page): ?>
                    <span class="page-link" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a class="page-link" href="<?= esc($query->url(['page' => $i]), 'attr') ?>"><span class="visually-hidden"><?= esc(lang('Sections.pager.page')) ?> </span><?= $i ?></a>
                <?php endif ?>
            </li>
        <?php endfor ?>
        <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>">
            <?php if ($page < $pages): ?>
                <a class="page-link" href="<?= esc($query->url(['page' => $page + 1]), 'attr') ?>" rel="next"><span aria-hidden="true">&raquo;</span><span class="visually-hidden"><?= esc(lang('Sections.pager.next')) ?></span></a>
            <?php else: ?>
                <span class="page-link"><span aria-hidden="true">&raquo;</span><span class="visually-hidden"><?= esc(lang('Sections.pager.next')) ?></span></span>
            <?php endif ?>
        </li>
    </ul>
</nav>
