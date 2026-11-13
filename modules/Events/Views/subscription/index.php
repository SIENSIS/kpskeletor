<?php
/**
 * "Calendar subscription" (`Subscription::index`): the personal iCal feed.
 *
 * @var string                                                                $layout
 * @var array<string, mixed>|null                                             $row
 * @var bool                                                                  $active
 * @var string|null                                                           $url     shown only right after (re)generating
 * @var string|null                                                           $webcal
 * @var list<array{id: int, slug: string, name: string, color: string|null}> $categories
 * @var list<array<string, mixed>>                                            $calendars
 * @var list<int>                                                             $selectedCategories
 * @var list<string>                                                          $selectedCalendars
 * @var string|null                                                           $publicUrl
 * @var \CodeIgniter\I18n\Time|null                                           $lastAccess
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if ($url !== null): ?>
    <div class="alert alert-success" role="status">
        <p class="mb-2"><i class="bi bi-rss me-1" aria-hidden="true"></i><strong><?= esc(lang('Events.subscription.newUrl')) ?></strong></p>
        <label for="events-ical-url" class="form-label small"><?= esc(lang('Events.subscription.copyNow')) ?></label>
        <div class="input-group mb-2">
            <input type="text" class="form-control font-monospace" id="events-ical-url" value="<?= esc($url, 'attr') ?>" readonly autocomplete="off" spellcheck="false">
            <button type="button" class="btn btn-outline-secondary" data-component="events/copy" data-options="<?= esc((string) json_encode(['target' => 'events-ical-url', 'copied' => lang('Events.subscription.copied')]), 'attr') ?>">
                <i class="bi bi-clipboard me-1" aria-hidden="true"></i><?= esc(lang('Events.subscription.copy')) ?>
            </button>
        </div>
        <a class="btn btn-sm btn-success" href="<?= esc((string) $webcal, 'attr') ?>"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i><?= esc(lang('Events.subscription.openApp')) ?></a>
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-6">
        <?php ob_start() ?>
        <p class="text-body-secondary"><?= esc(lang('Events.subscription.intro')) ?></p>
        <?php if ($active): ?>
            <dl class="row small mb-3">
                <dt class="col-sm-5"><?= esc(lang('Events.subscription.state')) ?></dt>
                <dd class="col-sm-7"><?= ui_badge(lang('Events.subscription.active'), 'success', 'bi bi-check-circle') ?></dd>
                <dt class="col-sm-5"><?= esc(lang('Events.subscription.since')) ?></dt>
                <dd class="col-sm-7"><?= ui_date(is_string($row['created_at'] ?? null) ? $row['created_at'] : null, 'datetime') ?></dd>
                <dt class="col-sm-5"><?= esc(lang('Events.subscription.lastAccess')) ?></dt>
                <dd class="col-sm-7"><?= $lastAccess === null ? esc(lang('Events.subscription.never')) : ui_date($lastAccess->toDateTimeString(), 'datetime') ?></dd>
            </dl>
            <p class="small text-body-secondary"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Events.subscription.hashed')) ?></p>
        <?php else: ?>
            <p class="mb-3"><?= ui_badge(lang($row === null ? 'Events.subscription.none' : 'Events.subscription.revokedState'), 'secondary', 'bi bi-slash-circle') ?></p>
        <?php endif ?>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="<?= esc(site_url('backend/calendar/subscription/generate'), 'attr') ?>"<?= $active ? ' data-confirm="' . esc(lang('Events.subscription.regenerateConfirm'), 'attr') . '"' : '' ?>>
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i><?= esc(lang($active ? 'Events.subscription.regenerate' : 'Events.subscription.generate')) ?></button>
            </form>
            <?php if ($active): ?>
                <form method="post" action="<?= esc(site_url('backend/calendar/subscription/revoke'), 'attr') ?>" data-confirm="<?= esc(lang('Events.subscription.revokeConfirm'), 'attr') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1" aria-hidden="true"></i><?= esc(lang('Events.subscription.revoke')) ?></button>
                </form>
            <?php endif ?>
        </div>
        <p class="small text-body-secondary mt-3 mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= esc(lang('Events.subscription.reauthNote')) ?></p>
        <?= theme_component('card', ['title' => lang('Events.subscription.feed'), 'body' => (string) ob_get_clean()]) ?>

        <?php if ($active): ?>
            <?php ob_start() ?>
            <p class="small text-body-secondary"><?= esc(lang('Events.subscription.filtersHelp')) ?></p>
            <?php if (count($calendars) > 1): ?>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6"><?= esc(lang('Events.calendars.title')) ?></legend>
                    <?php foreach ($calendars as $i => $calendar): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="calendars[]" id="ical-cal-<?= $i ?>" value="<?= esc($calendar['id'], 'attr') ?>"<?= in_array($calendar['id'], $selectedCalendars, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="ical-cal-<?= $i ?>"><?= esc($calendar['label']) ?></label>
                        </div>
                    <?php endforeach ?>
                </fieldset>
            <?php endif ?>
            <?php if ($categories !== []): ?>
                <fieldset class="mb-3">
                    <legend class="form-label fs-6"><?= esc(lang('Events.subscription.categories')) ?></legend>
                    <?php foreach ($categories as $category): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="categories[]" id="ical-cat-<?= (int) $category['id'] ?>" value="<?= (int) $category['id'] ?>"<?= in_array($category['id'], $selectedCategories, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="ical-cat-<?= (int) $category['id'] ?>"><?= esc($category['name']) ?></label>
                        </div>
                    <?php endforeach ?>
                </fieldset>
            <?php endif ?>
            <?= theme_component('form_layout', [
                'action'      => site_url('backend/calendar/subscription/filters'),
                'fields'      => (string) ob_get_clean(),
                'title'       => lang('Events.subscription.filters'),
                'submitLabel' => lang('Events.subscription.saveFilters'),
            ]) ?>
        <?php endif ?>
    </div>
    <div class="col-xl-6">
        <?php ob_start() ?>
        <p class="small text-body-secondary"><?= esc(lang('Events.subscription.howIntro')) ?></p>
        <?php foreach (['synology', 'google', 'outlook', 'apple', 'thunderbird'] as $client): ?>
            <h3 class="h6 mt-3"><?= esc(lang('Events.subscription.clients.' . $client . '.name')) ?></h3>
            <p class="small mb-0"><?= esc(lang('Events.subscription.clients.' . $client . '.steps')) ?></p>
        <?php endforeach ?>
        <p class="small text-body-secondary mt-3 mb-0"><?= esc(lang('Events.subscription.refreshNote')) ?></p>
        <?php if ($publicUrl !== null): ?>
            <p class="small mt-3 mb-0"><?= esc(lang('Events.subscription.publicFeed')) ?> <code><?= esc($publicUrl) ?></code></p>
        <?php endif ?>
        <?= theme_component('card', ['title' => lang('Events.subscription.how'), 'body' => (string) ob_get_clean()]) ?>
    </div>
</div>
<?= $this->endSection() ?>
