<?php
/**
 * Rich text (the Core's editor, `core/forms/editor`), one per language when translated.
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
    $required  = $field->isRequired() && ($locale === '' || $locale === $default);
    ?>
    <div class="kp-control"<?= $locale !== '' ? ' data-locale="' . esc($locale, 'attr') . '"' : '' ?>>
        <label class="form-label" for="<?= esc($inputId, 'attr') ?>"><?= esc($field->getLabel()) ?><?php if ($locale !== ''): ?> <span class="badge text-bg-light border kp-locale-badge"><?= esc($language) ?></span><?php endif ?><?= $required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
        <textarea class="form-control" id="<?= esc($inputId, 'attr') ?>" name="<?= esc($inputName, 'attr') ?>" rows="6"<?= $view->editorAttributes() ?>><?= esc(is_scalar($current) ? (string) $current : '') ?></textarea>
    </div>
<?php endforeach ?>
