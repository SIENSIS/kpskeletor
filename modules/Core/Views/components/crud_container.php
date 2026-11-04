<?php
/**
 * Frame of a kpcrudy screen: a card around the library's output.
 *
 * @var string      $content HTML rendered by kpcrudy (already escaped by the library)
 * @var string|null $title
 * @var string|null $resource resource name (data attribute for scripts and tests)
 */
?>
<section class="card crud-container shadow-sm"<?= ! empty($resource) ? ' data-resource="' . esc($resource) . '"' : '' ?>>
    <?php if (! empty($title)): ?>
        <div class="card-header"><h2 class="h6 mb-0"><?= esc($title) ?></h2></div>
    <?php endif ?>
    <div class="card-body">
        <div class="table-responsive-wrapper"><?= $content ?? '' ?></div>
    </div>
</section>
