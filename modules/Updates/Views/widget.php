<?php
/**
 * Widget "System version" (`Modules\Updates\Widgets\SystemVersion`): installed and latest
 * versions from the last check, modules with a new version, the last check's error.
 *
 * @var string                $installed
 * @var string|null           $latest
 * @var array<string, string> $newer      name (`Core` = the application) => version
 * @var string|null           $checkedAt
 * @var string|null           $error
 * @var string                $url
 */
?>
<p class="d-flex align-items-center gap-2 mb-1">
    <?php if (isset($newer['Core'])): ?>
        <i class="bi bi-arrow-up-circle-fill text-warning" aria-hidden="true"></i>
        <span data-update-available><?= esc(lang('Updates.widget.available', [$newer['Core']])) ?></span>
    <?php elseif ($checkedAt !== null && $error === null): ?>
        <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
        <span><?= esc(lang('Updates.msg.upToDate')) ?></span>
    <?php else: ?>
        <i class="bi bi-question-circle text-body-secondary" aria-hidden="true"></i>
        <span><?= esc(lang('Updates.widget.unknown')) ?></span>
    <?php endif ?>
</p>
<p class="small text-body-secondary mb-1"><?= esc(lang('Updates.installed')) ?>: <strong><?= esc($installed) ?></strong><?php if ($latest !== null && $latest !== ''): ?> · <?= esc(lang('Updates.latest')) ?>: <strong><?= esc($latest) ?></strong><?php endif ?></p>
<?php $modules = array_diff_key($newer, ['Core' => true]) ?>
<?php if ($modules !== []): ?>
    <p class="small mb-1"><?= esc(lang('Updates.widget.modules', [implode(', ', array_map(static fn (string $name, string $version): string => $name . ' ' . $version, array_keys($modules), $modules))])) ?></p>
<?php endif ?>
<?php if ($error !== null): ?>
    <p class="small text-danger mb-1"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> <?= esc(lang('Updates.widget.error')) ?></p>
<?php endif ?>
<p class="small text-body-secondary mb-2"><?= esc(lang('Updates.checkedAt')) ?>: <?= $checkedAt !== null ? ui_date($checkedAt . ' UTC', 'datetime') : esc(lang('Updates.never')) ?></p>
<a class="small" href="<?= esc($url, 'attr') ?>"><?= esc(lang('Updates.widget.open')) ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
