<?php
/**
 * Form frame: a card with the fields, hidden values, the CSRF token and a footer with the save and
 * cancel buttons (kept reachable at the bottom of the screen).
 *
 * @var string                $action      form action (internal URL)
 * @var string|null           $method      'post' (default) or 'get'
 * @var string                $fields      HTML of the fields (already escaped)
 * @var string|null           $title
 * @var string|null           $submitLabel
 * @var string|null           $cancelUrl
 * @var array<string, string> $hidden
 * @var bool|null             $multipart
 * @var bool|null             $dirtyGuard  warn before leaving with unsaved changes (core/forms/dirty-guard.js)
 */
$method = strtolower($method ?? 'post') === 'get' ? 'get' : 'post';
?>
<form action="<?= esc(ui_safe_url($action ?? '')) ?>" method="<?= $method ?>" class="card form-layout shadow-sm"<?= ! empty($multipart) ? ' enctype="multipart/form-data"' : '' ?><?= ! empty($dirtyGuard) ? ' data-dirty-guard' : '' ?>>
    <?php if ($method === 'post'): ?><?= csrf_field() ?><?php endif ?>
    <?php foreach ($hidden ?? [] as $name => $value): ?>
        <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>">
    <?php endforeach ?>
    <?php if (! empty($title)): ?>
        <div class="card-header"><h2 class="h6 mb-0"><?= esc($title) ?></h2></div>
    <?php endif ?>
    <div class="card-body"><?= $fields ?? '' ?></div>
    <div class="card-footer form-actions d-flex flex-wrap gap-2 justify-content-end">
        <?php if (! empty($cancelUrl)): ?>
            <a href="<?= esc(ui_safe_url($cancelUrl)) ?>" class="btn btn-outline-secondary"><?= esc(lang('Core.ui.cancel')) ?></a>
        <?php endif ?>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc($submitLabel ?? lang('Core.ui.save')) ?></button>
    </div>
</form>
