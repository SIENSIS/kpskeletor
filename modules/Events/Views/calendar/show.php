<?php
/**
 * An event in the back-office calendar (`CalendarView::show`) and the user's reminder.
 *
 * @var string               $layout
 * @var array<string, mixed> $event
 * @var int|null             $reminder
 * @var list<int>            $options
 * @var string               $formUrl
 * @var string               $backUrl
 * @var string|null          $editUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-3">
    <div class="col-12 col-lg-8">
        <article class="card shadow-sm">
            <div class="card-body">
                <div class="mb-3"><?= \Modules\Events\Controllers\Events::visibilityBadge((string) $event['visibility']) ?></div>
                <?= theme_view('Modules\Events\Views\calendar\_details', ['event' => $event]) ?>
            </div>
            <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="<?= esc($backUrl, 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Events.backToCalendar')) ?></a>
                <?php if ($editUrl !== null): ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= esc($editUrl, 'attr') ?>"><i class="bi bi-pencil" aria-hidden="true"></i> <?= esc(lang('Events.edit')) ?></a>
                <?php endif ?>
            </div>
        </article>
    </div>
    <div class="col-12 col-lg-4">
        <section class="card shadow-sm">
            <div class="card-body">
                <h2 class="h6"><i class="bi bi-alarm" aria-hidden="true"></i> <?= esc(lang('Events.reminder')) ?></h2>
                <?php if ($event['status'] === 'cancelled'): ?>
                    <p class="small text-body-secondary mb-0"><?= esc(lang('Events.reminderCancelled')) ?></p>
                <?php else: ?>
                    <form method="post" action="<?= esc($formUrl, 'attr') ?>">
                        <?= csrf_field() ?>
                        <label class="form-label small" for="events-reminder"><?= esc(lang('Events.reminderLabel')) ?></label>
                        <select class="form-select form-select-sm mb-2" id="events-reminder" name="minutes">
                            <option value=""><?= esc(lang('Events.reminderNone')) ?></option>
                            <?php foreach ($options as $minutes): ?>
                                <option value="<?= $minutes ?>"<?= $reminder === $minutes ? ' selected' : '' ?>><?= esc(lang('Events.before.' . ($minutes % 1440 === 0 ? 'days' : ($minutes % 60 === 0 ? 'hours' : 'minutes')), [$minutes % 1440 === 0 ? intdiv($minutes, 1440) : ($minutes % 60 === 0 ? intdiv($minutes, 60) : $minutes)])) ?></option>
                            <?php endforeach ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><?= esc(lang('Events.reminderSave')) ?></button>
                    </form>
                <?php endif ?>
            </div>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
