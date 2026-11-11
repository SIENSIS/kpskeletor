<?php
/**
 * Page template "sidebar": the article and, beside it, the subpages the visitor may read.
 *
 * @var string                     $layout
 * @var list<array<string, mixed>> $children
 * @var Closure(int): ?string      $childUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?= $this->include(theme_resolve('Modules\Pages\Views\partials\article')) ?>
    </div>
    <aside class="col-lg-4" aria-labelledby="page-subpages-title">
        <?php if ($children !== []): ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 card-title" id="page-subpages-title"><?= esc(lang('Pages.subpages')) ?></h2>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($children as $child): ?>
                            <?php $url = $childUrl((int) $child['id']); ?>
                            <?php if ($url !== null): ?>
                                <li class="py-1">
                                    <a href="<?= esc($url, 'attr') ?>"><?= esc((string) $child['title']) ?></a>
                                    <?php if ((string) $child['summary'] !== ''): ?>
                                        <div class="small text-body-secondary"><?= esc((string) $child['summary']) ?></div>
                                    <?php endif ?>
                                </li>
                            <?php endif ?>
                        <?php endforeach ?>
                    </ul>
                </div>
            </div>
        <?php endif ?>
    </aside>
</div>
<?= $this->endSection() ?>
