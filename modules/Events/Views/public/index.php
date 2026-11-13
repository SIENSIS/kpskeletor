<?php
/**
 * The public calendar (`PublicCalendar::index`): public events only.
 *
 * @var string                                                                $layout
 * @var string                                                                $title
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories
 * @var int                                                                   $selected
 * @var array<string, mixed>                                                  $options
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<h1 class="h2 mb-3"><?= esc($title) ?></h1>
<?= theme_view('Modules\Events\Views\calendar\_filter', ['categories' => $categories, 'selected' => $selected, 'options' => $options]) ?>
<?= $this->endSection() ?>
