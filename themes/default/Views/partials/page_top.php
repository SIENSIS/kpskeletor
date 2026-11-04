<?php
/**
 * Page header of a layout: `$pageHeader` (data of components/page_header) or, for screens that
 * only pass `$title` and `$breadcrumbs` (kpcrudy's setLayoutData()), the breadcrumbs.
 *
 * @var array<string, mixed>|null                         $pageHeader
 * @var list<array{label: string, url?: string|null}>|null $breadcrumbs
 */
?>
<?php if (! empty($pageHeader)): ?>
    <?= theme_component('page_header', $pageHeader) ?>
<?php elseif (! empty($breadcrumbs)): ?>
    <div class="mb-3"><?= theme_component('breadcrumbs', ['items' => $breadcrumbs]) ?></div>
<?php endif ?>
