<?php
/**
 * A boolean setting as a switch (a hidden 0 is posted when it is off).
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var string                        $value '1' or '0'
 * @var string|null                   $error
 */
?>
<div class="col-12 settings-field">
    <div class="form-check form-switch">
        <?php if (! $field->readOnly): ?><input type="hidden" name="<?= esc($field->name, 'attr') ?>" value="0"><?php endif ?>
        <input class="form-check-input<?= $error !== null ? ' is-invalid' : '' ?>" type="checkbox" role="switch" id="<?= esc($id, 'attr') ?>"
            name="<?= esc($field->name, 'attr') ?>" value="1" aria-describedby="<?= esc($id, 'attr') ?>-help"<?= $value === '1' ? ' checked' : '' ?><?= $field->readOnly ? ' disabled' : '' ?>>
        <label class="form-check-label" for="<?= esc($id, 'attr') ?>"><?= esc(lang($field->label)) ?></label>
    </div>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
