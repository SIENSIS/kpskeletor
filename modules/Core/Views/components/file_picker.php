<?php
/**
 * File-picker field (`core/forms/file-picker`, Modules\Core\Storage\Picker\PickerField::render()).
 * The hidden input is the form value (file keys); without JavaScript the current value is kept.
 *
 * @var string                    $name
 * @var string                    $id
 * @var string                    $value
 * @var list<array<string, mixed>> $items    the current files (PickerField::describe())
 * @var string                    $mode     upload | browse | both
 * @var bool                      $multiple
 * @var array<string, mixed>|null $options  component options (null: read only)
 * @var string|null               $label
 * @var string|null               $help
 * @var string|null               $error
 * @var bool                      $disabled
 */
$helpId = $id . '-help';
?>
<div class="file-picker<?= ! empty($error) ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>"<?php if ($options !== null): ?> data-component="core/forms/file-picker" data-options="<?= esc((string) json_encode($options, JSON_UNESCAPED_SLASHES), 'attr') ?>"<?php endif ?>>
    <?php if (! empty($label)): ?>
        <div class="form-label" id="<?= esc($id, 'attr') ?>-label"><?= esc($label) ?></div>
    <?php endif ?>
    <input type="hidden" name="<?= esc($name, 'attr') ?>" value="<?= esc($value, 'attr') ?>" data-picker-value>
    <ul class="file-picker-items list-unstyled d-flex flex-wrap gap-2 mb-2" data-picker-items aria-live="polite">
        <?php foreach ($items as $item): ?>
            <li class="file-picker-item card" data-key="<?= esc((string) $item['key'], 'attr') ?>">
                <?php if (! empty($item['image'])): ?>
                    <img class="file-picker-thumb card-img-top" src="<?= esc((string) ($item['thumbnail'] ?? $item['url']), 'attr') ?>" alt="">
                <?php else: ?>
                    <span class="file-picker-icon card-img-top d-flex align-items-center justify-content-center"><i class="bi bi-file-earmark" aria-hidden="true"></i></span>
                <?php endif ?>
                <span class="file-picker-name small text-truncate px-2 pt-1"><?= esc((string) $item['name']) ?></span>
                <?php if (! $disabled): ?>
                    <button type="button" class="btn btn-sm btn-link text-danger file-picker-remove" data-picker-remove aria-label="<?= esc(lang('Core.picker.remove', [(string) $item['name']]), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
    <?php if (! $disabled): ?>
        <div class="file-picker-actions d-flex flex-wrap gap-2 align-items-center">
            <?php if ($mode !== 'browse'): ?>
                <label class="btn btn-sm btn-outline-primary mb-0 file-picker-upload" data-picker-upload hidden>
                    <i class="bi bi-upload me-1" aria-hidden="true"></i><?= esc(lang('Core.picker.upload')) ?>
                    <input type="file" class="visually-hidden" data-picker-input<?= $multiple ? ' multiple' : '' ?><?php if (! empty($options['accept'])): ?> accept="<?= esc((string) $options['accept'], 'attr') ?>"<?php endif ?><?= ! empty($help) ? ' aria-describedby="' . esc($helpId, 'attr') . '"' : '' ?>>
                </label>
            <?php endif ?>
            <?php if ($mode !== 'upload'): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-picker-browse hidden><i class="bi bi-folder2-open me-1" aria-hidden="true"></i><?= esc(lang('Core.picker.browse')) ?></button>
            <?php endif ?>
            <progress class="file-picker-progress" max="100" value="0" data-picker-progress hidden></progress>
        </div>
        <noscript><p class="small text-body-secondary mb-0"><?= esc(lang('Core.picker.noScript')) ?></p></noscript>
    <?php endif ?>
    <div class="invalid-feedback d-block" data-picker-error role="alert"<?= empty($error) ? ' hidden' : '' ?>><?= esc((string) ($error ?? '')) ?></div>
    <?php if (! empty($help)): ?>
        <div id="<?= esc($helpId, 'attr') ?>" class="form-text"><?= esc($help) ?></div>
    <?php endif ?>
</div>
