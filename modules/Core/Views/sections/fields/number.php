<?php
/**
 * A whole number.
 *
 * @var \Modules\Core\Sections\Fields\NumberField $field
 * @var string                                    $name
 * @var string                                    $id
 * @var mixed                                     $value
 */
?>
<label class="form-label" for="<?= esc($id, 'attr') ?>"><?= esc($field->getLabel()) ?></label>
<input type="number" class="form-control kp-input-number" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc(is_scalar($value) ? (string) $value : '', 'attr') ?>" min="<?= (int) $field->getMin() ?>" max="<?= (int) $field->getMax() ?>" step="1"<?= $field->isRequired() ? ' required' : '' ?>>
