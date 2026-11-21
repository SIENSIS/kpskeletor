<?php
/**
 * One field of a section form (`SectionFormView`): the control of its type
 * (`sections/fields/<type>`), its help and its error.
 *
 * @var \Modules\Core\Sections\Fields\Field $field
 * @var string                              $name    the input name (`f[title]`, `f[items][0][title]`)
 * @var string                              $id      a unique id prefix
 * @var string                              $path    the field's path for errors (`items.0.title`)
 * @var mixed                               $value   the posted shape
 * @var array{path: string, message: string}|null $error
 * @var \Modules\Core\Sections\SectionFormView $view
 */
$fieldError = $view->errorFor($path);
?>
<div class="kp-field kp-field-<?= esc($field->type(), 'attr') ?> mb-3<?= $fieldError !== null ? ' has-error' : '' ?>" data-field="<?= esc($path, 'attr') ?>">
    <?= $view->control($field, $name, $id, $path, $value) ?>
    <?php if ($field->getHelp() !== ''): ?>
        <div class="form-text" id="<?= esc($id, 'attr') ?>-help"><?= esc($field->getHelp()) ?></div>
    <?php endif ?>
    <?php if ($fieldError !== null): ?>
        <div class="invalid-feedback d-block" role="alert"><?= esc($fieldError) ?></div>
    <?php endif ?>
</div>
