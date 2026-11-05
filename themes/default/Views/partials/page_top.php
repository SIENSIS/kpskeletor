<?php
/**
 * Page header of a layout: `$pageHeader` (data of components/page_header) or, for screens that
 * only pass `$title` and `$breadcrumbs` (kpcrudy CRUDs: the CRUD's title and
 * `setLayoutData(['breadcrumbs' => …])`), a page header built from them; with breadcrumbs only,
 * the breadcrumbs.
 *
 * @var array<string, mixed>|null                         $pageHeader
 * @var list<array{label: string, url?: string|null}>|null $breadcrumbs
 * @var string|null                                       $title
 */
?>
<?php if (! empty($pageHeader)): ?>
    <?= theme_component('page_header', $pageHeader) ?>
<?php elseif (! empty($breadcrumbs) && isset($title) && is_string($title) && $title !== ''): ?>
    <?= theme_component('page_header', ['title' => $title, 'breadcrumbs' => $breadcrumbs]) ?>
<?php elseif (! empty($breadcrumbs)): ?>
    <div class="mb-3"><?= theme_component('breadcrumbs', ['items' => $breadcrumbs]) ?></div>
<?php endif ?>
