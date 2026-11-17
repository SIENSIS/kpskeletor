<?php
/**
 * Widget "Security status" (`Modules\Core\Widgets\SecurityStatus`): the summary of the last run.
 *
 * @var string|null                                           $status
 * @var array{ok: int, info: int, warning: int, error: int}|null $counts
 * @var list<string>                                          $errors
 * @var string|null                                           $checkedAt
 * @var string                                                $url
 */
$icons = ['ok' => 'bi-shield-check text-success', 'info' => 'bi-shield-check text-success', 'warning' => 'bi-shield-exclamation text-warning', 'error' => 'bi-shield-x text-danger'];
?>
<?php if ($status === null || $counts === null): ?>
    <p class="text-body-secondary mb-2"><i class="bi bi-shield" aria-hidden="true"></i> <?= esc(lang('SecurityCheck.widget.never')) ?></p>
<?php else: ?>
    <p class="d-flex align-items-center gap-2 mb-1" data-security-widget="<?= esc($status, 'attr') ?>">
        <i class="bi <?= esc($icons[$status], 'attr') ?> fs-4" aria-hidden="true"></i>
        <span><?= esc(lang('SecurityCheck.overall.' . $status)) ?></span>
    </p>
    <p class="small mb-1"><?= esc(lang('SecurityCheck.summary', [$counts['ok'], $counts['info'], $counts['warning'], $counts['error']])) ?></p>
    <?php if ($errors !== []): ?>
        <ul class="small text-danger mb-1 ps-3">
            <?php foreach ($errors as $title): ?>
                <li><?= esc($title) ?></li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <p class="small text-body-secondary mb-2"><?= esc(lang('SecurityCheck.checkedAt')) ?>: <?= ui_date((string) $checkedAt . ' UTC', 'datetime') ?></p>
<?php endif ?>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('SecurityCheck.widget.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
