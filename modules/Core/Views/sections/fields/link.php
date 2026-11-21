<?php
/**
 * A link: what it points to (a page, another module's link type, an address or a route) and its
 * label per language. `core/sections/form` shows only the control of the chosen kind.
 *
 * @var \Modules\Core\Sections\Fields\LinkField $field
 * @var string                                  $name
 * @var string                                  $id
 * @var string                                  $path
 * @var mixed                                   $value
 * @var \Modules\Core\Sections\SectionFormView  $view
 */
$value   = is_array($value) ? $value : [];
$type    = is_string($value['type'] ?? null) ? $value['type'] : '';
$values  = is_array($value['value'] ?? null) ? $value['value'] : [];
$labels  = is_array($value['label'] ?? null) ? $value['label'] : [];
$types   = $view->linkTypes();
$choices = $view->linkChoices();
$invalid = $view->errorFor($path . '.value') !== null || $view->errorFor($path) !== null;
?>
<fieldset class="kp-link-field border rounded p-3" data-link-field>
    <legend class="form-label fs-6 float-none w-auto px-1 mb-1"><?= esc($field->getLabel()) ?><?= $field->isRequired() ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></legend>
    <div class="row g-2">
        <div class="col-md-4">
            <label class="form-label small" for="<?= esc($id, 'attr') ?>-type"><?= esc(lang('Sections.link.type')) ?></label>
            <select class="form-select form-select-sm" id="<?= esc($id, 'attr') ?>-type" name="<?= esc($name, 'attr') ?>[type]" data-link-type>
                <option value=""><?= esc(lang('Sections.link.none')) ?></option>
                <?php foreach ($types as $key => $label): ?>
                    <option value="<?= esc($key, 'attr') ?>"<?= $key === $type ? ' selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div class="col-md-8">
            <?php foreach ($types as $key => $label): ?>
                <?php $current = is_scalar($values[$key] ?? null) ? (string) $values[$key] : ''; ?>
                <div data-link-value="<?= esc($key, 'attr') ?>"<?= $key !== $type ? ' hidden' : '' ?>>
                    <label class="form-label small" for="<?= esc($id . '-v-' . md5($key), 'attr') ?>"><?= esc(lang('Sections.link.value', [$label])) ?></label>
                    <?php if (isset($choices[$key])): ?>
                        <select class="form-select form-select-sm<?= $invalid && $key === $type ? ' is-invalid' : '' ?>" id="<?= esc($id . '-v-' . md5($key), 'attr') ?>" name="<?= esc($name . '[value][' . $key . ']', 'attr') ?>">
                            <option value=""></option>
                            <?php foreach ($choices[$key] as $choice => $choiceLabel): ?>
                                <option value="<?= esc((string) $choice, 'attr') ?>"<?= (string) $choice === $current ? ' selected' : '' ?>><?= esc($choiceLabel) ?></option>
                            <?php endforeach ?>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control form-control-sm<?= $invalid && $key === $type ? ' is-invalid' : '' ?>" id="<?= esc($id . '-v-' . md5($key), 'attr') ?>" name="<?= esc($name . '[value][' . $key . ']', 'attr') ?>" value="<?= esc($current, 'attr') ?>" maxlength="500" spellcheck="false" placeholder="<?= esc($key === 'url' ? 'https://… /…' : '', 'attr') ?>">
                    <?php endif ?>
                </div>
            <?php endforeach ?>
        </div>
        <?php if ($field->hasLabel()): ?>
            <?php foreach ($view->locales() as $locale => $language): ?>
                <?php $labelPath = $path . '.label.' . $locale; ?>
                <div class="col-12" data-locale="<?= esc($locale, 'attr') ?>" data-link-label>
                    <label class="form-label small" for="<?= esc($id . '-label-' . $locale, 'attr') ?>"><?= esc(lang('Sections.link.label')) ?> <span class="badge text-bg-light border kp-locale-badge"><?= esc($language) ?></span></label>
                    <input type="text" class="form-control form-control-sm<?= $view->errorFor($labelPath) !== null ? ' is-invalid' : '' ?>" id="<?= esc($id . '-label-' . $locale, 'attr') ?>" name="<?= esc($name . '[label][' . $locale . ']', 'attr') ?>" value="<?= esc(is_scalar($labels[$locale] ?? null) ? (string) $labels[$locale] : '', 'attr') ?>" maxlength="<?= \Modules\Core\Sections\Fields\LinkField::LABEL_MAX ?>">
                </div>
            <?php endforeach ?>
        <?php endif ?>
    </div>
</fieldset>
