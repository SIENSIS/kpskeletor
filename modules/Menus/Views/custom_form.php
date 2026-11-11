<?php
/**
 * A custom group or link of the back-office menu.
 *
 * @var string                                    $layout
 * @var int|null                                  $id
 * @var array<string, mixed>                      $link
 * @var array<string, string>                     $types
 * @var array<string, array<int|string, string>>  $choices
 * @var array<string, string>                     $parents
 * @var array<string, string>                     $zones
 * @var array<string, string>                     $groups
 * @var list<string>                              $permissions
 * @var array<string, string>                     $locales
 * @var string                                    $action
 * @var string|null                               $deleteUrl
 * @var string                                    $backUrl
 */
$old    = static fn (string $key, mixed $default): mixed => old($key) ?? $default;
$labels = $old('labels', $link['labels']);
$labels = is_array($labels) ? $labels : [];
$isLink = $link['kind'] === 'link';
$type   = (string) $old('type', $link['type']);
$value  = (string) $link['value'];
$choice = old('choice');

if (is_array($choice) && is_scalar($choice[$type] ?? null)) {
    $value = (string) $choice[$type];
} elseif (old('value') !== null) {
    $value = (string) old('value');
}

$chosenGroups = array_map('strval', (array) $old('groups', $link['groups']));
$parent       = (string) $old('parent', $link['parent'] ?? '');
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form method="post" action="<?= esc($action, 'attr') ?>" class="card shadow-sm" data-dirty-guard<?= $isLink ? ' data-component="menus/link-form"' : '' ?>>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">
    <input type="hidden" name="kind" value="<?= esc((string) $link['kind'], 'attr') ?>">
    <div class="card-body">
        <p class="small text-body-secondary"><?= esc(lang($isLink ? 'Menus.custom.linkHelp' : 'Menus.custom.groupHelp')) ?></p>
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.label')) ?></legend>
            <?php foreach ($locales as $locale => $language): ?>
                <div class="col-md-4">
                    <label class="form-label" for="f-label-<?= esc($locale, 'attr') ?>"><?= esc($language) ?><?= $locale === 'ca' ? ' *' : '' ?></label>
                    <input type="text" class="form-control" id="f-label-<?= esc($locale, 'attr') ?>" name="labels[<?= esc($locale, 'attr') ?>]" maxlength="100" value="<?= esc((string) ($labels[$locale] ?? ''), 'attr') ?>"<?= $locale === 'ca' ? ' required' : '' ?>>
                </div>
            <?php endforeach ?>
        </fieldset>
        <?php if ($isLink): ?>
            <fieldset class="row g-3 mb-4">
                <legend class="h6"><?= esc(lang('Menus.fields.target')) ?></legend>
                <?= theme_view('Modules\Menus\Views\target_fields', ['types' => $types, 'choices' => $choices, 'type' => $type, 'value' => $value]) ?>
                <div class="col-md-4">
                    <label class="form-label" for="f-window"><?= esc(lang('Menus.fields.window')) ?></label>
                    <select class="form-select" id="f-window" name="target">
                        <?php foreach (['_self' => 'Menus.window.self', '_blank' => 'Menus.window.blank'] as $key => $label): ?>
                            <option value="<?= esc($key, 'attr') ?>"<?= $key === $old('target', $link['target']) ? ' selected' : '' ?>><?= esc(lang($label)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            </fieldset>
        <?php endif ?>
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.place')) ?></legend>
            <div class="col-md-5">
                <label class="form-label" for="f-parent"><?= esc(lang('Menus.fields.parent')) ?></label>
                <select class="form-select" id="f-parent" name="parent">
                    <option value=""><?= esc(lang('Menus.fields.root')) ?></option>
                    <?php foreach ($parents as $key => $label): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $key === $parent ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f-zone"><?= esc(lang('Menus.fields.zone')) ?></label>
                <select class="form-select" id="f-zone" name="zone">
                    <?php foreach ($zones as $key => $label): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $key === $old('zone', $link['zone']) ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f-position"><?= esc(lang('Menus.fields.position')) ?></label>
                <input type="number" class="form-control" id="f-position" name="position" min="0" max="9999" step="1" required value="<?= esc((string) $old('position', $link['position']), 'attr') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="f-icon"><?= esc(lang('Menus.fields.icon')) ?></label>
                <input type="text" class="form-control" id="f-icon" name="icon" maxlength="60" placeholder="bi bi-link-45deg" value="<?= esc((string) $old('icon', $link['icon']), 'attr') ?>">
            </div>
        </fieldset>
        <?php if ($isLink): ?>
            <fieldset class="row g-3">
                <legend class="h6"><?= esc(lang('Menus.fields.visibility')) ?></legend>
                <div class="col-md-5">
                    <label class="form-label" for="f-permission"><?= esc(lang('Menus.fields.permission')) ?></label>
                    <select class="form-select" id="f-permission" name="permission" aria-describedby="f-permission-help">
                        <option value=""><?= esc(lang('Menus.fields.noPermission')) ?></option>
                        <?php foreach ($permissions as $permission): ?>
                            <option value="<?= esc($permission, 'attr') ?>"<?= $permission === $old('permission', $link['permission']) ? ' selected' : '' ?>><?= esc($permission) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text" id="f-permission-help"><?= esc(lang('Menus.help.permission')) ?></div>
                </div>
                <div class="col-md-7">
                    <span class="form-label d-block"><?= esc(lang('Menus.fields.groups')) ?></span>
                    <?php foreach ($groups as $name => $title): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="f-group-<?= esc($name, 'attr') ?>" name="groups[]" value="<?= esc($name, 'attr') ?>"<?= in_array($name, $chosenGroups, true) ? ' checked' : '' ?>>
                            <label class="form-check-label" for="f-group-<?= esc($name, 'attr') ?>"><?= esc($title) ?></label>
                        </div>
                    <?php endforeach ?>
                    <div class="form-text"><?= esc(lang('Menus.help.linkGroups')) ?></div>
                </div>
            </fieldset>
        <?php endif ?>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><?= esc(lang('Menus.cancel')) ?></a>
        <?php if ($deleteUrl !== null): ?>
            <button type="submit" class="btn btn-outline-danger ms-auto" formaction="<?= esc($deleteUrl, 'attr') ?>" data-confirm="<?= esc(lang('Menus.custom.deleteConfirm'), 'attr') ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Menus.delete')) ?></button>
        <?php endif ?>
    </div>
</form>
<?= $this->endSection() ?>
