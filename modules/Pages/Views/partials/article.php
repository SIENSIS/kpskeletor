<?php
/**
 * The article of a page (shared by the templates): preview notice, breadcrumbs, title, featured
 * image, summary and body (sanitised with HTMLPurifier when saved and again here).
 *
 * @var array<string, mixed>                       $page
 * @var string                                     $body
 * @var string|null                                $image
 * @var list<array{label: string, url?: string}>  $trail
 * @var bool                                       $preview
 * @var bool                                       $home
 */
?>
<?php if ($preview): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start" role="status">
        <i class="bi bi-eye" aria-hidden="true"></i>
        <div>
            <strong><?= esc(lang('Pages.previewNotice')) ?></strong>
            <?= esc(lang('Pages.previewStatus', [lang('Pages.statuses.' . \Modules\Pages\Services\PageVisibility::displayStatus($page)), lang('Pages.visibilities.' . (string) $page['visibility'])])) ?>
        </div>
    </div>
<?php endif ?>
<?php if ($trail !== []): ?>
    <div class="mb-3"><?= theme_component('breadcrumbs', ['items' => $trail]) ?></div>
<?php endif ?>
<article class="page-article">
    <?php if (! $home || $preview): ?>
        <header class="mb-4">
            <h1 class="display-6 text-break"><?= esc((string) $page['title']) ?></h1>
        </header>
    <?php endif ?>
    <?php if ($image !== null): ?>
        <img class="img-fluid rounded mb-4 page-image" src="<?= esc($image, 'attr') ?>" alt="" loading="lazy">
    <?php endif ?>
    <?php if ((string) $page['summary'] !== ''): ?>
        <p class="lead"><?= esc((string) $page['summary']) ?></p>
    <?php endif ?>
    <div class="page-body text-break"><?= $body ?></div>
</article>
