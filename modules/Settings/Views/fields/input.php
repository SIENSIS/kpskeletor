<?php
/**
 * A text-like setting: string, email, url, int, float, color.
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var string                        $value
 * @var string|null                   $error
 * @var string                        $inputType
 */
?>
<div class="col-md-6 settings-field">
    <label for="<?= esc($id, 'attr') ?>" class="form-label"><?= esc(lang($field->label)) ?></label>
    <input type="<?= esc($inputType, 'attr') ?>" class="<?= $inputType === 'color' ? 'form-control form-control-color' : 'form-control' ?><?= $error !== null ? ' is-invalid' : '' ?>"
        id="<?= esc($id, 'attr') ?>" name="<?= esc($field->name, 'attr') ?>" value="<?= esc($value, 'attr') ?>"
        aria-describedby="<?= esc($id, 'attr') ?>-help"<?= $field->type === 'float' ? ' step="any"' : '' ?><?= $field->readOnly ? ' disabled' : '' ?>>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
