<?php
/**
 * An icon of Bootstrap Icons: a text input (`bi-star`) that `core/sections/icon-field` completes
 * with a searchable grid.
 *
 * @var \Modules\Core\Sections\Fields\Field $field
 * @var string                              $name
 * @var string                              $id
 * @var string                              $path
 * @var mixed                               $value
 * @var \Modules\Core\Sections\SectionFormView $view
 */
$current = is_string($value) ? $value : '';
$invalid = $view->errorFor($path) !== null;
?>
<div data-component="core/sections/icon-field" class="kp-icon-field">
    <label class="form-label" for="<?= esc($id, 'attr') ?>"><?= esc($field->getLabel()) ?><?= $field->isRequired() ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
    <div class="input-group">
        <span class="input-group-text kp-icon-preview" aria-hidden="true"><i class="bi <?= esc($current !== '' ? $current : 'bi-square', 'attr') ?>" data-icon-preview></i></span>
        <input type="text" class="form-control<?= $invalid ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= esc($current, 'attr') ?>" maxlength="60" pattern="bi-[a-z0-9\-]+" placeholder="bi-star" autocomplete="off" spellcheck="false" data-icon-input aria-describedby="<?= esc($id, 'attr') ?>-icon-help">
        <button type="button" class="btn btn-outline-secondary" data-icon-toggle aria-expanded="false" aria-controls="<?= esc($id, 'attr') ?>-grid" hidden><i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i><?= esc(lang('Sections.icon.choose')) ?></button>
    </div>
    <div class="form-text" id="<?= esc($id, 'attr') ?>-icon-help"><?= esc(lang('Sections.icon.help')) ?></div>
    <div class="kp-icon-grid card card-body mt-2" id="<?= esc($id, 'attr') ?>-grid" data-icon-panel hidden>
        <label class="visually-hidden" for="<?= esc($id, 'attr') ?>-search"><?= esc(lang('Sections.icon.search')) ?></label>
        <input type="search" class="form-control form-control-sm mb-2" id="<?= esc($id, 'attr') ?>-search" placeholder="<?= esc(lang('Sections.icon.search'), 'attr') ?>" data-icon-search autocomplete="off">
        <div class="kp-icon-options" role="listbox" aria-label="<?= esc(lang('Sections.icon.choose'), 'attr') ?>" data-icon-options></div>
        <p class="small text-body-secondary mb-0 mt-2" data-icon-count aria-live="polite"></p>
    </div>
</div>
