<?php
/**
 * The fields of a section form (`SectionFormView::render()`), inside the module's `<form>`: the
 * language tabs (`core/sections/form`), the type's fields, the appearance (style, background,
 * spacing, width) and the advanced options (anchor, hidden).
 *
 * @var \Modules\Core\Sections\SectionFormView $view
 * @var \Modules\Core\Sections\SectionType     $type
 * @var string                                 $fields  HTML of the fields
 * @var array{style: string, background: string, background_image: string, spacing: string, width: string, anchor: string} $options
 * @var bool                                   $hidden
 * @var array{path: string, message: string}|null $error
 * @var string                                 $icons   the icon names (comma separated) when the form has icon fields
 */
$locales = $view->locales();
$styles  = $type->styles();
?>
<div class="kp-section-form" data-component="core/sections/form"<?= $icons !== '' ? ' data-icons="' . esc($icons, 'attr') . '"' : '' ?>>
    <?php if ($error !== null): ?>
        <div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= esc($error['message']) ?></div>
    <?php endif ?>
    <?php if (count($locales) > 1): ?>
        <div class="kp-locale-tabs d-flex flex-wrap align-items-center gap-2 mb-3" data-locale-tabs hidden>
            <span class="small text-body-secondary" id="kp-locale-tabs-label"><?= esc(lang('Sections.form.languages')) ?></span>
            <div class="btn-group btn-group-sm" role="group" aria-labelledby="kp-locale-tabs-label">
                <?php foreach ($locales as $locale => $language): ?>
                    <button type="button" class="btn btn-outline-primary" data-locale-tab="<?= esc($locale, 'attr') ?>" aria-pressed="false"><?= esc($language) ?></button>
                <?php endforeach ?>
            </div>
        </div>
    <?php endif ?>
    <fieldset class="mb-4">
        <legend class="h6 border-bottom pb-1"><?= esc(lang('Sections.form.content')) ?></legend>
        <?= $fields ?>
    </fieldset>
    <fieldset class="mb-4">
        <legend class="h6 border-bottom pb-1"><?= esc(lang('Sections.form.appearance')) ?></legend>
        <div class="row g-3">
            <?php if ($styles !== []): ?>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label" for="so-style"><?= esc(lang('Sections.options.style')) ?></label>
                    <select class="form-select" id="so-style" name="o[style]">
                        <?php foreach ($styles as $key => $label): ?>
                            <option value="<?= esc($key, 'attr') ?>"<?= $key === $options['style'] ? ' selected' : '' ?>><?= esc(lang($label)) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
            <?php endif ?>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="so-background"><?= esc(lang('Sections.options.background')) ?></label>
                <select class="form-select" id="so-background" name="o[background]" data-option-background>
                    <?php foreach ($type->backgrounds() as $background): ?>
                        <option value="<?= esc($background, 'attr') ?>"<?= $background === $options['background'] ? ' selected' : '' ?>><?= esc(lang('Sections.backgrounds.' . $background)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="so-spacing"><?= esc(lang('Sections.options.spacing')) ?></label>
                <select class="form-select" id="so-spacing" name="o[spacing]">
                    <?php foreach (\Modules\Core\Sections\SectionOptions::SPACINGS as $spacing): ?>
                        <option value="<?= esc($spacing, 'attr') ?>"<?= $spacing === $options['spacing'] ? ' selected' : '' ?>><?= esc(lang('Sections.spacings.' . $spacing)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label" for="so-width"><?= esc(lang('Sections.options.width')) ?></label>
                <select class="form-select" id="so-width" name="o[width]">
                    <?php foreach (\Modules\Core\Sections\SectionOptions::WIDTHS as $width): ?>
                        <option value="<?= esc($width, 'attr') ?>"<?= $width === $options['width'] ? ' selected' : '' ?>><?= esc(lang('Sections.widths.' . $width)) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?php if (in_array('image', $type->backgrounds(), true)): ?>
                <div class="col-12" data-background-image<?= $options['background'] !== 'image' ? ' hidden' : '' ?>>
                    <div class="form-label"><?= esc(lang('Sections.options.background_image')) ?></div>
                    <?= $view->picker('o[background_image]', 'so-background-image', $options['background_image'], $view->errorFor('options.background_image')) ?>
                    <div class="form-text"><?= esc(lang('Sections.options.backgroundImageHelp')) ?></div>
                </div>
            <?php endif ?>
        </div>
    </fieldset>
    <fieldset>
        <legend class="h6 border-bottom pb-1"><?= esc(lang('Sections.form.advanced')) ?></legend>
        <div class="row g-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label" for="so-anchor"><?= esc(lang('Sections.options.anchor')) ?></label>
                <input type="text" class="form-control<?= $view->errorFor('options.anchor') !== null ? ' is-invalid' : '' ?>" id="so-anchor" name="o[anchor]" value="<?= esc($options['anchor'], 'attr') ?>" maxlength="<?= \Modules\Core\Sections\SectionOptions::ANCHOR_MAX ?>" pattern="[a-z][a-z0-9]*(-[a-z0-9]+)*" spellcheck="false" aria-describedby="so-anchor-help">
                <div class="form-text" id="so-anchor-help"><?= esc(lang('Sections.options.anchorHelp')) ?></div>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="so-hidden" name="hidden" value="1"<?= $hidden ? ' checked' : '' ?>>
                    <label class="form-check-label" for="so-hidden"><?= esc(lang('Sections.options.hidden')) ?></label>
                </div>
            </div>
        </div>
    </fieldset>
</div>
