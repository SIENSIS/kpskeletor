<?php
/**
 * The back-office calendar (`CalendarView::index`): events the user can see.
 *
 * @var string                                                                $layout
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories
 * @var int                                                                   $selected
 * @var array<string, mixed>                                                  $options
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= theme_view('Modules\Events\Views\calendar\_filter', ['categories' => $categories, 'selected' => $selected, 'options' => $options]) ?>
<?= $this->endSection() ?>
