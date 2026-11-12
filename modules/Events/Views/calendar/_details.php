<?php
/**
 * Details of an event (back office and public site). `when` comes from `ui_date()` (escaped
 * there); the description was sanitised with HTMLPurifier by `EventRepository`.
 *
 * @var array<string, mixed> $event
 */
?>
<dl class="row mb-0 events-details">
    <dt class="col-sm-3"><i class="bi bi-clock" aria-hidden="true"></i> <?= esc(lang('Events.when')) ?></dt>
    <dd class="col-sm-9"><?= $event['when'] ?>
        <?php if ($event['status'] !== 'confirmed'): ?>
            <?= \Modules\Events\Controllers\Events::statusBadge((string) $event['status']) ?>
        <?php endif ?>
    </dd>
    <?php if ($event['recurrence'] !== null): ?>
        <dt class="col-sm-3"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= esc(lang('Events.repeats')) ?></dt>
        <dd class="col-sm-9"><?= esc((string) $event['recurrence']) ?></dd>
    <?php endif ?>
    <?php if (($event['location'] ?? '') !== '' && $event['location'] !== null): ?>
        <dt class="col-sm-3"><i class="bi bi-geo-alt" aria-hidden="true"></i> <?= esc(lang('Events.fields.location')) ?></dt>
        <dd class="col-sm-9"><?= esc((string) $event['location']) ?></dd>
    <?php endif ?>
    <?php if ($event['category_name'] !== null): ?>
        <dt class="col-sm-3"><i class="bi bi-tag" aria-hidden="true"></i> <?= esc(lang('Events.fields.category_id')) ?></dt>
        <dd class="col-sm-9"><?= esc((string) $event['category_name']) ?></dd>
    <?php endif ?>
    <?php if (($event['organiser'] ?? '') !== '' && $event['organiser'] !== null): ?>
        <dt class="col-sm-3"><i class="bi bi-person" aria-hidden="true"></i> <?= esc(lang('Events.fields.organiser')) ?></dt>
        <dd class="col-sm-9"><?= esc((string) $event['organiser']) ?></dd>
    <?php endif ?>
    <?php if (is_string($event['url'] ?? null) && preg_match('#^https?://#i', $event['url']) === 1): ?>
        <dt class="col-sm-3"><i class="bi bi-link-45deg" aria-hidden="true"></i> <?= esc(lang('Events.fields.url')) ?></dt>
        <dd class="col-sm-9 text-break"><a href="<?= esc($event['url'], 'attr') ?>" rel="noopener noreferrer" target="_blank"><?= esc($event['url']) ?></a></dd>
    <?php endif ?>
</dl>
<?php if ($event['description'] !== ''): ?>
    <hr>
    <div class="events-description text-break">
        <?= $event['description'] ?>
    </div>
<?php endif ?>
