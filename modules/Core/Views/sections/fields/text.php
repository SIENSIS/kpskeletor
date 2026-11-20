<?php
/**
 * Text, textarea and select controls (one per language when the field is translated).
 *
 * @var \Modules\Core\Sections\Fields\Field $field
 * @var string                              $name
 * @var string                              $id
 * @var string                              $path
 * @var mixed                               $value
 * @var \Modules\Core\Sections\SectionFormView $view
 */
$controls = $field->isTranslated() ? $view->locales() : ['' => ''];
$default  = $view->defaultLocale();
?>
<?php foreach ($controls as $locale => $language): ?>
    <?php
    $inputName = $locale === '' ? $name : $name . '[' . $locale . ']';
    $inputId   = $locale === '' ? $id : $id . '-' . $locale;
    $current   = $locale === '' ? $value : (is_array($value) ? ($value[$locale] ?? '') : '');
    $current   = is_scalar($current) ? (string) $current : '';
    $current   = $current === '' && $field instanceof \Modules\Core\Sections\Fields\SelectField ? (string) $field->emptyValue() : $current;
    $required  = $field->isRequired() && ($locale === '' || $locale === $default);
    $invalid   = $view->errorFor($locale === '' ? $path : $path . '.' . $locale) !== null;
    $describe  = $field->getHelp() !== '' ? ' aria-describedby="' . esc($id . '-help', 'attr') . '"' : '';
    ?>
    <div class="kp-control"<?= $locale !== '' ? ' data-locale="' . esc($locale, 'attr') . '"' : '' ?>>
        <label class="form-label" for="<?= esc($inputId, 'attr') ?>"><?= esc($field->getLabel()) ?><?php if ($locale !== ''): ?> <span class="badge text-bg-light border kp-locale-badge"><?= esc($language) ?></span><?php endif ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
        <?php if ($field instanceof \Modules\Core\Sections\Fields\SelectField): ?>
            <select class="form-select<?= $invalid ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($inputName, 'attr') ?>"<?= $describe ?>>
                <?php foreach ($field->getOptions() as $key => $label): ?>
                    <option value="<?= esc((string) $key, 'attr') ?>"<?= (string) $key === $current ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        <?php elseif ($field instanceof \Modules\Core\Sections\Fields\TextareaField): ?>
            <textarea class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($inputName, 'attr') ?>" rows="3" maxlength="<?= (int) $field->getMaxLength() ?>"<?= $required ? ' required' : '' ?><?= $describe ?>><?= esc($current) ?></textarea>
        <?php elseif ($field instanceof \Modules\Core\Sections\Fields\TextField): ?>
            <input type="text" class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($inputName, 'attr') ?>" value="<?= esc($current, 'attr') ?>" maxlength="<?= (int) $field->getMaxLength() ?>"<?= $required ? ' required' : '' ?><?= $describe ?>>
        <?php endif ?>
    </div>
<?php endforeach ?>
