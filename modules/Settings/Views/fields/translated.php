<?php
/**
 * A translated text (type `translated`): one input per available locale of the site, each with
 * its language's name; an empty text uses the default language's one (docs/settings.md).
 *
 * @var \Modules\Settings\Forms\Field  $field
 * @var string                         $id
 * @var array<string, string>          $texts  locale => text shown
 * @var array<string, string|null>     $errors locale => validation error
 * @var string|null                    $error
 */
?>
<div class="col-12 settings-field">
    <span class="form-label d-block" id="<?= esc($id, 'attr') ?>-label"><?= esc(lang($field->label)) ?></span>
    <?php foreach ($texts as $locale => $text): ?>
        <?php $inputId = $id . '-' . $locale; $localeError = $errors[$locale] ?? null; ?>
        <div class="input-group mb-1<?= $localeError !== null ? ' has-validation' : '' ?>">
            <label class="input-group-text settings-locale" for="<?= esc($inputId, 'attr') ?>" lang="<?= esc($locale, 'attr') ?>"><?= esc(\Modules\Core\I18n\Locales::name($locale)) ?></label>
            <input type="text" class="form-control<?= $localeError !== null ? ' is-invalid' : '' ?>" id="<?= esc($inputId, 'attr') ?>"
                name="<?= esc($field->name, 'attr') ?>[<?= esc($locale, 'attr') ?>]" value="<?= esc($text, 'attr') ?>" lang="<?= esc($locale, 'attr') ?>"
                aria-describedby="<?= esc($id, 'attr') ?>-label <?= esc($id, 'attr') ?>-help"<?= $field->readOnly ? ' disabled' : '' ?>>
            <?php if ($localeError !== null): ?>
                <div class="invalid-feedback"><?= esc($localeError) ?></div>
            <?php endif ?>
        </div>
    <?php endforeach ?>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
