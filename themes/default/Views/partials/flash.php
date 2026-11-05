<?php
/**
 * Session messages (`message`/`success`, `info`, `warning`, `error`; kpcrudy's `flashKeys` use
 * the same keys). Shown as toasts by the `core/ui/flash` component; the dismissible alerts are
 * the fallback without JavaScript.
 */
$types    = ['message' => 'success', 'success' => 'success', 'info' => 'info', 'warning' => 'warning', 'error' => 'danger'];
$messages = [];

foreach ($types as $key => $variant) {
    $text = session()->getFlashdata($key);

    if (is_string($text) && $text !== '') {
        $messages[] = ['text' => $text, 'variant' => $variant];
    }
}
?>
<?php if ($messages !== []): ?>
<div class="flash-messages" data-component="core/ui/flash">
    <?php foreach ($messages as $message): ?>
        <div class="alert alert-<?= $message['variant'] ?> alert-dismissible fade show" role="<?= $message['variant'] === 'danger' ? 'alert' : 'status' ?>" data-flash="<?= $message['variant'] ?>">
            <span data-flash-text><?= esc($message['text']) ?></span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= esc(lang('Core.ui.close')) ?>"></button>
        </div>
    <?php endforeach ?>
</div>
<?php endif ?>
