<?php
/**
 * The back-office calendar (`CalendarView::index`): events the user can see.
 *
 * @var string                                                                $layout
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories
 * @var int                                                                   $selected
 * @var array<string, mixed>                                                  $options
 * @var list<array<string, mixed>>                                            $calendars
 * @var list<string>                                                          $hidden
 * @var string                                                                $saveUrl
 */
$options['toggles'] = count($calendars) > 1 ? 'events-calendars' : null;
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if (count($calendars) > 1): ?>
    <form method="post" action="<?= esc($saveUrl, 'attr') ?>" id="events-calendars" class="d-flex flex-wrap align-items-center gap-3 mb-3 events-calendars">
        <?= csrf_field() ?>
        <span class="small fw-semibold"><?= esc(lang('Events.calendars.title')) ?>:</span>
        <?php foreach ($calendars as $i => $calendar): ?>
            <div class="form-check form-check-inline mb-0">
                <input class="form-check-input" type="checkbox" name="calendars[]" id="events-cal-<?= $i ?>" value="<?= esc($calendar['id'], 'attr') ?>"<?= in_array($calendar['id'], $hidden, true) ? '' : ' checked' ?>>
                <label class="form-check-label small" for="events-cal-<?= $i ?>">
                    <?= esc($calendar['label']) ?>
                </label>
            </div>
        <?php endforeach ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary" data-calendar-submit><?= esc(lang('Events.calendars.apply')) ?></button>
    </form>
<?php endif ?>
<?= theme_view('Modules\Events\Views\calendar\_filter', ['categories' => $categories, 'selected' => $selected, 'options' => $options]) ?>
<?= $this->endSection() ?>
