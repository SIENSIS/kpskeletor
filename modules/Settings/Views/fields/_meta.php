<?php
/**
 * Help text, the value used when nothing is stored, and the validation error of a field.
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var string|null                   $error
 */
$default = $field->defaultText();
?>
<?php if ($error !== null): ?>
    <div class="invalid-feedback d-block" id="<?= esc($id, 'attr') ?>-error"><?= esc($error) ?></div>
<?php endif ?>
<div class="form-text" id="<?= esc($id, 'attr') ?>-help">
    <?php if ($field->help !== null): ?><?= esc(lang($field->help)) ?><?php endif ?>
    <?php if ($field->isOverridden()): ?>
        <span class="d-block"><?= ui_badge(lang('Settings.ui.changed'), 'info') ?>
        <?= esc(lang('Settings.ui.default', [$default === '' ? lang('Settings.ui.empty') : ($field->type === 'bool' ? lang($default === '1' ? 'Settings.ui.on' : 'Settings.ui.off') : str_replace("\n", ', ', $default))])) ?></span>
    <?php endif ?>
</div>
