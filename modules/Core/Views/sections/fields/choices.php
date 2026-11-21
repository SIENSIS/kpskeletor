<?php
/**
 * Several choices (checkboxes) of a `ChoicesField`. An empty hidden input sends the field when no
 * box is ticked.
 *
 * @var \Modules\Core\Sections\Fields\ChoicesField $field
 * @var string                                     $name
 * @var string                                     $id
 * @var string                                     $path
 * @var mixed                                      $value
 * @var \Modules\Core\Sections\SectionFormView     $view
 */
$chosen  = is_array($value) ? array_map('strval', array_filter($value, 'is_scalar')) : [];
$options = $field->getOptions();
$invalid = $view->errorFor($path) !== null;
?>
<fieldset class="kp-choices"<?= $field->getHelp() !== '' ? ' aria-describedby="' . esc($id . '-help', 'attr') . '"' : '' ?>>
    <legend class="form-label fs-6 mb-1"><?= esc($field->getLabel()) ?><?= $field->isRequired() ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></legend>
    <input type="hidden" name="<?= esc($name, 'attr') ?>" value="">
    <?php if ($options === []): ?>
        <p class="small text-body-secondary mb-0"><?= esc(lang('Sections.form.noChoices')) ?></p>
    <?php else: ?>
        <div class="d-flex flex-wrap gap-3">
            <?php $i = 0; foreach ($options as $key => $label): $i++; ?>
                <div class="form-check">
                    <input class="form-check-input<?= $invalid ? ' is-invalid' : '' ?>" type="checkbox" id="<?= esc($id . '-' . $i, 'attr') ?>" name="<?= esc($name . '[]', 'attr') ?>" value="<?= esc((string) $key, 'attr') ?>"<?= in_array((string) $key, $chosen, true) ? ' checked' : '' ?>>
                    <label class="form-check-label" for="<?= esc($id . '-' . $i, 'attr') ?>"><?= esc($label) ?></label>
                </div>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</fieldset>
