<?php
/**
 * Section `events.calendar`: the public calendar component (public events only) with an
 * optional category filter among the section's categories.
 * Override: `themes/<slug>/Views/overrides/Events/sections/calendar.php`.
 *
 * @var array<string, mixed>                                                  $values     heading, intro, categories, view, filter
 * @var array<string, mixed>                                                  $calendar   options of the `events/calendar` component
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories the filter's choices ([] = no filter)
 * @var string                                                                $filterId
 */
?>
<?= theme_view('Modules\Core\Views\sections\heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => false]) ?>
<div class="kp-events-calendar">
    <?= theme_view('Modules\Events\Views\calendar\_filter', ['categories' => $categories, 'selected' => 0, 'options' => $calendar, 'filterId' => $filterId]) ?>
</div>
