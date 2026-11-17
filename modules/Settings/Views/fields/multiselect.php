<?php
/**
 * A setting with several fixed choices (type `multiselect`): one checkbox per option.
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var list<string>                  $checked the checked values
 * @var string|null                   $error
 */
?>
<div class="col-12 settings-field">
    <fieldset aria-describedby="<?= esc($id, 'attr') ?>-help">
        <legend class="form-label fs-6 mb-1"><?= esc(lang($field->label)) ?></legend>
        <?php foreach ($field->options as $option => $label): ?>
            <?php $optionId = $id . '-' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $option); ?>
            <div class="form-check form-check-inline">
                <input class="form-check-input<?= $error !== null ? ' is-invalid' : '' ?>" type="checkbox" id="<?= esc($optionId, 'attr') ?>"
                    name="<?= esc($field->name, 'attr') ?>[]" value="<?= esc((string) $option, 'attr') ?>"<?= in_array((string) $option, $checked, true) ? ' checked' : '' ?><?= $field->readOnly ? ' disabled' : '' ?>>
                <label class="form-check-label" for="<?= esc($optionId, 'attr') ?>"><?= esc($field->literalOptions ? $label : lang($label)) ?></label>
            </div>
        <?php endforeach ?>
    </fieldset>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
