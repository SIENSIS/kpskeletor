<?php
/**
 * Retouch one entry of the back-office menu for a scope: labels, icon, hidden, parent and zone.
 * Empty fields keep the generated value (shown as the placeholder).
 *
 * @var string                    $layout
 * @var string                    $key
 * @var string|null               $scope
 * @var string                    $scopeName
 * @var array<string, mixed>      $entry
 * @var array<string, mixed>      $base
 * @var string                    $baseText
 * @var array<string, mixed>|null $stored
 * @var string|null               $parent
 * @var array<string, string>     $parents
 * @var array<string, string>     $zones
 * @var array<string, string>     $locales
 * @var string                    $action
 * @var string                    $backUrl
 * @var bool                      $canReset
 */
$labels = old('labels') ?? ($stored['labels'] ?? []);
$labels = is_array($labels) ? $labels : [];
$hidden = old('hidden') !== null ? old('hidden') === '1' : (($entry['hidden'] ?? false) === true);
$zone   = (string) (old('zone') ?? ($entry['zone'] ?? 'sidebar'));
$chosen = (string) (old('parent') ?? ($parent ?? ''));
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<p class="text-body-secondary small">
    <?= esc(lang('Menus.backend.entryHelp', [$scopeName])) ?>
    <code><?= esc($key) ?></code><?php if ((string) ($entry['permission'] ?? '') !== ''): ?> · <?= esc(lang('Menus.backend.permission')) ?> <code><?= esc((string) $entry['permission']) ?></code><?php endif ?>
</p>
<form method="post" action="<?= esc($action, 'attr') ?>" class="card shadow-sm" data-dirty-guard>
    <?= csrf_field() ?>
    <input type="hidden" name="key" value="<?= esc($key, 'attr') ?>">
    <input type="hidden" name="group" value="<?= esc((string) $scope, 'attr') ?>">
    <div class="card-body">
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.label')) ?></legend>
            <?php foreach ($locales as $locale => $language): ?>
                <div class="col-md-4">
                    <label class="form-label" for="f-label-<?= esc($locale, 'attr') ?>"><?= esc($language) ?></label>
                    <input type="text" class="form-control" id="f-label-<?= esc($locale, 'attr') ?>" name="labels[<?= esc($locale, 'attr') ?>]" maxlength="100" placeholder="<?= esc($baseText, 'attr') ?>" value="<?= esc((string) ($labels[$locale] ?? ''), 'attr') ?>">
                </div>
            <?php endforeach ?>
            <div class="col-12 form-text mt-1"><?= esc(lang('Menus.help.rename')) ?></div>
        </fieldset>
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.place')) ?></legend>
            <div class="col-md-6">
                <label class="form-label" for="f-parent"><?= esc(lang('Menus.fields.parent')) ?></label>
                <select class="form-select" id="f-parent" name="parent" aria-describedby="f-parent-help">
                    <option value=""><?= esc(lang('Menus.fields.root')) ?></option>
                    <?php foreach ($parents as $value => $label): ?>
                        <option value="<?= esc($value, 'attr') ?>"<?= $value === $chosen ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
                <div class="form-text" id="f-parent-help"><?= esc(lang('Menus.help.parent')) ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f-zone"><?= esc(lang('Menus.fields.zone')) ?></label>
                <select class="form-select" id="f-zone" name="zone">
                    <?php foreach ($zones as $value => $label): ?>
                        <option value="<?= esc($value, 'attr') ?>"<?= $value === $zone ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f-icon"><?= esc(lang('Menus.fields.icon')) ?></label>
                <input type="text" class="form-control" id="f-icon" name="icon" maxlength="60" placeholder="<?= esc((string) ($base['icon'] ?? ''), 'attr') ?>" value="<?= esc((string) (old('icon') ?? ($stored['icon'] ?? '')), 'attr') ?>" aria-describedby="f-icon-help">
                <div class="form-text" id="f-icon-help"><?= esc(lang('Menus.help.icon')) ?></div>
            </div>
        </fieldset>
        <div class="form-check form-switch">
            <input type="hidden" name="hidden" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="f-hidden" name="hidden" value="1"<?= $hidden ? ' checked' : '' ?> aria-describedby="f-hidden-help">
            <label class="form-check-label" for="f-hidden"><?= esc(lang('Menus.fields.hidden')) ?></label>
            <div class="form-text" id="f-hidden-help"><?= esc(lang('Menus.help.hidden')) ?></div>
        </div>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><?= esc(lang('Menus.cancel')) ?></a>
        <?php if ($canReset && $stored !== null): ?>
            <button type="submit" class="btn btn-outline-warning ms-auto" formaction="<?= esc(site_url('backend/menus/backend/reset'), 'attr') ?>" name="reset" value="entry" data-confirm="<?= esc(lang('Menus.backend.resetEntryConfirm', [$baseText]), 'attr') ?>"><i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i><?= esc(lang('Menus.backend.reset')) ?></button>
        <?php endif ?>
    </div>
</form>
<?= $this->endSection() ?>
