<?php
/**
 * Widget "Backups" (`Modules\Backup\Widgets\Backups`): the last backup, its result and size, and
 * the next scheduled run.
 *
 * @var array<string, mixed>|null $last
 * @var string|null               $next
 * @var bool                      $scheduled
 * @var string                    $url
 */
?>
<?php if ($last === null): ?>
    <p class="d-flex align-items-center gap-2 mb-2">
        <i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i>
        <span><?= esc(lang('Backup.widget.none')) ?></span>
    </p>
<?php else: ?>
    <p class="d-flex align-items-center gap-2 mb-1">
        <?php if ($last['result'] === 'success'): ?>
            <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
            <span><?= esc(lang('Backup.widget.lastOk')) ?></span>
        <?php else: ?>
            <i class="bi bi-x-octagon-fill text-danger" aria-hidden="true"></i>
            <span><?= esc(lang('Backup.widget.lastFailed')) ?></span>
        <?php endif ?>
    </p>
    <p class="small text-body-secondary mb-2">
        <?= ui_date((string) $last['created_at'], 'datetime') ?> · <?= esc(lang('Backup.type.' . $last['type'])) ?>
        <?php if ($last['file'] !== null): ?> · <?= esc(ui_bytes((int) $last['size'])) ?><?php endif ?>
    </p>
<?php endif ?>
<p class="small text-body-secondary mb-2">
    <?php if ($scheduled && $next !== null): ?>
        <?= esc(lang('Backup.widget.next')) ?> <?= ui_date($next, 'datetime') ?>
    <?php else: ?>
        <?= esc(lang('Backup.widget.notScheduled')) ?>
    <?php endif ?>
</p>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Backup.widget.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
