<?php
/**
 * An image: the file picker (upload or choose), "decorative" and the alternative text per language.
 *
 * @var \Modules\Core\Sections\Fields\Field $field
 * @var string                              $name
 * @var string                              $id
 * @var string                              $path
 * @var mixed                               $value
 * @var \Modules\Core\Sections\SectionFormView $view
 */
$value      = is_array($value) ? $value : [];
$key        = is_string($value['key'] ?? null) ? $value['key'] : '';
$decorative = in_array($value['decorative'] ?? null, ['1', 1, true], true);
$alts       = is_array($value['alt'] ?? null) ? $value['alt'] : [];
$default    = $view->defaultLocale();
?>
<fieldset class="kp-image-field border rounded p-3" data-image-field>
    <legend class="form-label fs-6 float-none w-auto px-1 mb-1"><?= esc($field->getLabel()) ?><?= $field->isRequired() ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></legend>
    <?= $view->picker($name . '[key]', $id, $key, $view->errorFor($path) ?? null) ?>
    <div class="form-check mt-2">
        <input class="form-check-input" type="checkbox" id="<?= esc($id, 'attr') ?>-decorative" name="<?= esc($name, 'attr') ?>[decorative]" value="1"<?= $decorative ? ' checked' : '' ?> data-image-decorative>
        <label class="form-check-label" for="<?= esc($id, 'attr') ?>-decorative"><?= esc(lang('Sections.image.decorative')) ?></label>
    </div>
    <div class="row g-2 mt-1" data-image-alts<?= $decorative ? ' hidden' : '' ?>>
        <?php foreach ($view->locales() as $locale => $language): ?>
            <?php $altPath = $path . '.alt.' . $locale; ?>
            <div class="col-12" data-locale="<?= esc($locale, 'attr') ?>">
                <label class="form-label small" for="<?= esc($id . '-alt-' . $locale, 'attr') ?>"><?= esc(lang('Sections.image.alt')) ?> <span class="badge text-bg-light border kp-locale-badge"><?= esc($language) ?></span><?= $locale === $default ? ' <span class="text-danger" aria-hidden="true">*</span>' : '' ?></label>
                <input type="text" class="form-control form-control-sm<?= $view->errorFor($altPath) !== null ? ' is-invalid' : '' ?>" id="<?= esc($id . '-alt-' . $locale, 'attr') ?>" name="<?= esc($name . '[alt][' . $locale . ']', 'attr') ?>" value="<?= esc(is_scalar($alts[$locale] ?? null) ? (string) $alts[$locale] : '', 'attr') ?>" maxlength="<?= \Modules\Core\Sections\Fields\ImageField::ALT_MAX ?>">
            </div>
        <?php endforeach ?>
        <div class="col-12 form-text"><?= esc(lang('Sections.image.altHelp')) ?></div>
    </div>
</fieldset>
