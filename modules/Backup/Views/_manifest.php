<?php
/**
 * The contents of a backup (its manifest).
 *
 * @var array<string, mixed> $manifest
 */
$tables  = is_array($manifest['tables'] ?? null) ? $manifest['tables'] : [];
$areas   = is_array($manifest['contents']['files'] ?? null) ? $manifest['contents']['files'] : [];
$modules = is_array($manifest['modules'] ?? null) ? $manifest['modules'] : [];
?>
<dl class="row small mb-0">
    <dt class="col-sm-4"><?= esc(lang('Backup.field.date')) ?></dt>
    <dd class="col-sm-8"><?= ui_date((string) ($manifest['created_at'] ?? ''), 'datetime') ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.field.type')) ?></dt>
    <dd class="col-sm-8"><?= esc(lang('Backup.type.' . ($manifest['type'] ?? 'full'))) ?><?= ! empty($manifest['demo']) ? ' ' . ui_badge(lang('Backup.demo'), 'warning') : '' ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.field.version')) ?></dt>
    <dd class="col-sm-8">Core <?= esc((string) ($manifest['core'] ?? '?')) ?> · <?= esc((string) ($manifest['driver'] ?? '')) ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.form.tables')) ?></dt>
    <dd class="col-sm-8"><?= esc(lang('Backup.form.tablesCount', [count($tables), array_sum(array_map('intval', $tables))])) ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.form.files')) ?></dt>
    <dd class="col-sm-8"><?= $areas === [] ? '—' : esc(implode(', ', array_map('strval', $areas))) ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.form.env')) ?></dt>
    <dd class="col-sm-8"><?= ! empty($manifest['contents']['env']) ? esc(lang('Backup.form.yes')) : '—' ?></dd>
    <dt class="col-sm-4"><?= esc(lang('Backup.form.modules')) ?></dt>
    <dd class="col-sm-8 text-break"><?= esc(implode(', ', array_map(static fn ($name, $version): string => $name . ' ' . $version, array_keys($modules), $modules))) ?></dd>
</dl>
