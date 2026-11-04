<?php
/**
 * Session messages (`message`/`success`, `error`, `warning`), as dismissible alerts.
 * Toasts replace them in the front-end session.
 */
$types = ['message' => 'success', 'success' => 'success', 'warning' => 'warning', 'error' => 'danger'];
?>
<?php foreach ($types as $key => $variant): ?>
    <?php $text = session()->getFlashdata($key); ?>
    <?php if (is_string($text) && $text !== ''): ?>
        <div class="alert alert-<?= $variant ?> alert-dismissible fade show" role="<?= $variant === 'danger' ? 'alert' : 'status' ?>">
            <?= esc($text) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('Core.ui.close')) ?>"></button>
        </div>
    <?php endif ?>
<?php endforeach ?>
