<?php
/**
 * The target of a menu item or custom link: the type, then the address (URL or route) or one of
 * the link type's choices. Without JavaScript every field is shown; `menus/link-form` shows only
 * the one of the chosen type.
 *
 * @var array<string, string>                    $types   type => label
 * @var array<string, array<int|string, string>> $choices link type => value => label
 * @var string                                   $type
 * @var string                                   $value
 */
?>
<div class="col-md-4">
    <label class="form-label" for="f-type"><?= esc(lang('Menus.fields.type')) ?></label>
    <select class="form-select" id="f-type" name="type" data-link-type>
        <?php foreach ($types as $key => $label): ?>
            <option value="<?= esc($key, 'attr') ?>"<?= $key === $type ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach ?>
    </select>
</div>
<div class="col-md-8" data-link-field="url route">
    <label class="form-label" for="f-value"><?= esc(lang('Menus.fields.value')) ?></label>
    <input type="text" class="form-control" id="f-value" name="value" maxlength="500" value="<?= esc(in_array($type, ['url', 'route'], true) ? $value : '', 'attr') ?>" aria-describedby="f-value-help">
    <div class="form-text" id="f-value-help"><?= esc(lang('Menus.help.value')) ?></div>
</div>
<?php foreach ($choices as $key => $options): ?>
    <?php $fieldId = 'f-choice-' . preg_replace('/[^a-z0-9]/', '-', $key); ?>
    <div class="col-md-8" data-link-field="<?= esc($key, 'attr') ?>">
        <label class="form-label" for="<?= esc($fieldId, 'attr') ?>"><?= esc($types[$key] ?? $key) ?></label>
        <select class="form-select" id="<?= esc($fieldId, 'attr') ?>" name="choice[<?= esc($key, 'attr') ?>]">
            <option value=""><?= esc(lang('Menus.fields.choose')) ?></option>
            <?php foreach ($options as $optionValue => $optionLabel): ?>
                <option value="<?= esc((string) $optionValue, 'attr') ?>"<?= $key === $type && (string) $optionValue === $value ? ' selected' : '' ?>><?= esc($optionLabel) ?></option>
            <?php endforeach ?>
        </select>
    </div>
<?php endforeach ?>
