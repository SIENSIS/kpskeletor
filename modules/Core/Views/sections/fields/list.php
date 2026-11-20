<?php
/**
 * Repeated items (`core/sections/form`: add, remove and reorder; `__i__` in the template is
 * replaced by a new index). Without JavaScript the items shown are posted as they are.
 *
 * @var \Modules\Core\Sections\Fields\ListField $field
 * @var string                                  $name
 * @var string                                  $id
 * @var string                                  $path
 * @var mixed                                   $value
 * @var \Modules\Core\Sections\SectionFormView  $view
 */
$items = is_array($value) ? array_values($value) : [];
?>
<fieldset class="kp-list-field" data-list data-list-max="<?= (int) $field->getMax() ?>" data-list-min="<?= (int) $field->getMin() ?>">
    <legend class="form-label fs-6"><?= esc($field->getLabel()) ?> <span class="small text-body-secondary">(<?= esc(lang('Sections.form.itemsLimit', [$field->getMin(), $field->getMax()])) ?>)</span></legend>
    <ol class="list-unstyled kp-list-items mb-2" data-list-items>
        <?php foreach ($items as $index => $item): ?>
            <?= $view->listItem($field, $name, $id, $path, (string) $index, is_array($item) ? $item : [], $index + 1) ?>
        <?php endforeach ?>
    </ol>
    <template data-list-template><?= $view->listItem($field, $name, $id, $path, '__i__', [], 0) ?></template>
    <button type="button" class="btn btn-sm btn-outline-primary" data-list-add hidden><i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= esc(lang('Sections.form.addItem')) ?></button>
    <p class="visually-hidden" aria-live="polite" data-list-status></p>
</fieldset>
