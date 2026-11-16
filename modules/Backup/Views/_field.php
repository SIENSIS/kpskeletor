<?php
/**
 * One form field with its label, error and help (Backup forms).
 *
 * @var string                $name
 * @var string                $label
 * @var string                $type        input type
 * @var string|null           $value
 * @var string|null           $help
 * @var string|null           $autocomplete
 * @var array<string, string> $errors
 */
$id    = 'backup-' . str_replace('_', '-', $name);
$error = $errors[$name] ?? null;
?>
<div class="mb-3">
    <label for="<?= esc($id, 'attr') ?>" class="form-label"><?= esc($label) ?></label>
    <input type="<?= esc($type, 'attr') ?>" class="form-control<?= $error !== null ? ' is-invalid' : '' ?>" id="<?= esc($id, 'attr') ?>" name="<?= esc($name, 'attr') ?>"<?= isset($value) ? ' value="' . esc($value, 'attr') . '"' : '' ?> required autocomplete="<?= esc($autocomplete ?? 'off', 'attr') ?>"<?= ! empty($help) ? ' aria-describedby="' . esc($id, 'attr') . '-help"' : '' ?>>
    <?php if ($error !== null): ?><div class="invalid-feedback"><?= esc($error) ?></div><?php endif ?>
    <?php if (! empty($help)): ?><div id="<?= esc($id, 'attr') ?>-help" class="form-text"><?= esc($help) ?></div><?php endif ?>
</div>
