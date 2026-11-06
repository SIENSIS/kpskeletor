<?php
/**
 * A multi-line setting: text, a list (one entry per line) or JSON.
 *
 * @var \Modules\Settings\Forms\Field $field
 * @var string                        $id
 * @var string                        $value
 * @var string|null                   $error
 */
$rows = max(3, min(10, substr_count($value, "\n") + 2));
?>
<div class="col-12 settings-field">
    <label for="<?= esc($id, 'attr') ?>" class="form-label"><?= esc(lang($field->label)) ?></label>
    <?php if ($field->type === 'list'): ?><span class="small text-body-secondary ms-1">(<?= esc(lang('Settings.ui.onePerLine')) ?>)</span><?php endif ?>
    <textarea class="form-control<?= $field->type === 'text' ? '' : ' font-monospace' ?><?= $error !== null ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>"
        name="<?= esc($field->name, 'attr') ?>" rows="<?= $rows ?>" spellcheck="false"
        aria-describedby="<?= esc($id, 'attr') ?>-help"<?= $field->readOnly ? ' disabled' : '' ?>><?= esc($value) ?></textarea>
    <?= $this->include('Modules\Settings\Views\fields\_meta') ?>
</div>
