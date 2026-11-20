<?php
/**
 * Yes or no.
 *
 * @var \Modules\Core\Sections\Fields\Field $field
 * @var string                              $name
 * @var string                              $id
 * @var mixed                               $value
 */
?>
<div class="form-check form-switch">
    <input type="hidden" name="<?= esc($name, 'attr') ?>" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="1"<?= in_array($value, [true, '1', 1, 'on'], true) ? ' checked' : '' ?>>
    <label class="form-check-label" for="<?= esc($id, 'attr') ?>"><?= esc($field->getLabel()) ?></label>
</div>
