<?php
/**
 * A setting with fixed choices.
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var string                        $value
 * @var string|null                   $error
 */
?>
<div class="col-md-6 settings-field">
    <label for="<?= esc($id, 'attr') ?>" class="form-label"><?= esc(lang($field->label)) ?></label>
    <select class="form-select<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($field->name, 'attr') ?>"
        aria-describedby="<?= esc($id, 'attr') ?>-help"<?= $field->readOnly ? ' disabled' : '' ?>>
        <?php foreach ($field->options as $option => $label): ?>
            <option value="<?= esc($option, 'attr') ?>"<?= (string) $option === $value ? ' selected' : '' ?>><?= esc($field->literalOptions ? $label : lang($label)) ?></option>
        <?php endforeach ?>
    </select>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
