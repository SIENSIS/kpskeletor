<?php
/**
 * The form of a public menu item.
 *
 * @var string                                             $layout
 * @var array{id: int, name: string, location: string|null} $menu
 * @var int|null                                           $itemId
 * @var array<string, mixed>                               $item
 * @var array<string, string>                              $types
 * @var array<string, array<int|string, string>>           $choices
 * @var array<int, string>                                 $parents
 * @var array<int, string>                                 $groups
 * @var array<string, string>                              $locales
 * @var array<string, string>                              $visibilities
 * @var string                                             $action
 * @var string                                             $backUrl
 */
$old    = static fn (string $key, mixed $default): mixed => old($key) ?? $default;
$labels = $old('labels', $item['labels']);
$labels = is_array($labels) ? $labels : [];
$type   = (string) $old('type', $item['type']);
$value  = (string) $item['value'];
$choice = old('choice');

if (is_array($choice) && is_scalar($choice[$type] ?? null)) {
    $value = (string) $choice[$type];
} elseif (old('value') !== null) {
    $value = (string) old('value');
}

$chosen = array_map('intval', (array) $old('groups', $item['groups']));
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form method="post" action="<?= esc($action, 'attr') ?>" class="card shadow-sm" data-dirty-guard data-component="menus/link-form">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $itemId ?>">
    <div class="card-body">
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.label')) ?></legend>
            <?php foreach ($locales as $locale => $language): ?>
                <div class="col-md-4">
                    <label class="form-label" for="f-label-<?= esc($locale, 'attr') ?>"><?= esc($language) ?><?= $locale === 'ca' ? ' *' : '' ?></label>
                    <input type="text" class="form-control" id="f-label-<?= esc($locale, 'attr') ?>" name="labels[<?= esc($locale, 'attr') ?>]" maxlength="100" value="<?= esc((string) ($labels[$locale] ?? ''), 'attr') ?>"<?= $locale === 'ca' ? ' required' : '' ?>>
                </div>
            <?php endforeach ?>
        </fieldset>
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.target')) ?></legend>
            <?= theme_view('Modules\Menus\Views\target_fields', ['types' => $types, 'choices' => $choices, 'type' => $type, 'value' => $value]) ?>
            <div class="col-md-4">
                <label class="form-label" for="f-window"><?= esc(lang('Menus.fields.window')) ?></label>
                <select class="form-select" id="f-window" name="target">
                    <?php foreach (['_self' => 'Menus.window.self', '_blank' => 'Menus.window.blank'] as $key => $label): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $key === $old('target', $item['target']) ? ' selected' : '' ?>><?= esc(lang($label)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        </fieldset>
        <fieldset class="row g-3 mb-4">
            <legend class="h6"><?= esc(lang('Menus.fields.place')) ?></legend>
            <div class="col-md-6">
                <label class="form-label" for="f-parent"><?= esc(lang('Menus.fields.parent')) ?></label>
                <select class="form-select" id="f-parent" name="parent_id">
                    <option value=""><?= esc(lang('Menus.fields.root')) ?></option>
                    <?php foreach ($parents as $id => $label): ?>
                        <option value="<?= (int) $id ?>"<?= (string) $id === (string) $old('parent_id', $item['parent_id']) ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f-icon"><?= esc(lang('Menus.fields.icon')) ?></label>
                <input type="text" class="form-control" id="f-icon" name="icon" maxlength="60" placeholder="bi bi-star" value="<?= esc((string) $old('icon', $item['icon']), 'attr') ?>" aria-describedby="f-icon-help">
                <div class="form-text" id="f-icon-help"><?= esc(lang('Menus.help.icon')) ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="f-class"><?= esc(lang('Menus.fields.cssClass')) ?></label>
                <input type="text" class="form-control" id="f-class" name="css_class" maxlength="100" value="<?= esc((string) $old('css_class', $item['css_class']), 'attr') ?>">
            </div>
        </fieldset>
        <fieldset class="row g-3">
            <legend class="h6"><?= esc(lang('Menus.fields.visibility')) ?></legend>
            <div class="col-md-4">
                <label class="form-label" for="f-visibility"><?= esc(lang('Menus.fields.visibleTo')) ?></label>
                <select class="form-select" id="f-visibility" name="visibility">
                    <?php foreach ($visibilities as $key => $label): ?>
                        <option value="<?= esc($key, 'attr') ?>"<?= $key === $old('visibility', $item['visibility']) ? ' selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-8">
                <span class="form-label d-block"><?= esc(lang('Menus.fields.groups')) ?></span>
                <?php foreach ($groups as $id => $title): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" id="f-group-<?= (int) $id ?>" name="groups[]" value="<?= (int) $id ?>"<?= in_array((int) $id, $chosen, true) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="f-group-<?= (int) $id ?>"><?= esc($title) ?></label>
                    </div>
                <?php endforeach ?>
                <div class="form-text"><?= esc(lang('Menus.help.groups')) ?></div>
            </div>
        </fieldset>
    </div>
    <div class="card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Menus.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><?= esc(lang('Menus.cancel')) ?></a>
    </div>
</form>
<?= $this->endSection() ?>
