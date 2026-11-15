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
    <form method="post" action="<?= esc($saveUrl, 'attr') ?>" id="events-calendars" class="mb-3 events-calendars" aria-label="<?= esc(lang('Events.calendars.title'), 'attr') ?>">
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <span class="small fw-semibold me-1"><?= esc(lang('Events.calendars.title')) ?>:</span>
            <?php foreach ($calendars as $i => $calendar): ?>
                <div class="events-calendar-toggle d-inline-flex align-items-center border rounded-pill ps-2 pe-1 py-1"<?= $calendar['color'] !== null ? ' data-color="' . esc($calendar['color'], 'attr') . '"' : '' ?>>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="calendars[]" id="events-cal-<?= $i ?>" value="<?= esc($calendar['id'], 'attr') ?>"<?= in_array($calendar['id'], $hidden, true) ? '' : ' checked' ?>>
                        <label class="form-check-label small" for="events-cal-<?= $i ?>"><?= esc($calendar['label']) ?></label>
                    </div>
                    <button type="submit" name="only" value="<?= esc($calendar['id'], 'attr') ?>" class="btn btn-link btn-sm py-0 px-1 text-body-secondary" title="<?= esc(lang('Events.calendars.only', [$calendar['label']]), 'attr') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i><span class="visually-hidden"><?= esc(lang('Events.calendars.only', [$calendar['label']])) ?></span>
                    </button>
                </div>
            <?php endforeach ?>
            <button type="submit" name="all" value="1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye-fill me-1" aria-hidden="true"></i><?= esc(lang('Events.calendars.all')) ?></button>
            <button type="submit" class="btn btn-sm btn-outline-secondary" data-calendar-submit><?= esc(lang('Events.calendars.apply')) ?></button>
        </div>
    </form>
<?php endif ?>
<?= theme_view('Modules\Events\Views\calendar\_filter', ['categories' => $categories, 'selected' => $selected, 'options' => $options]) ?>
<?= $this->endSection() ?>
