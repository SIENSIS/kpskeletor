<?php
/**
 * Error of the current form and validation errors (auth screens).
 *
 * @var string|null                $error
 * @var array<string, string>|null $errors
 */
?>
<?php if (! empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?= esc($error) ?></div>
<?php endif ?>
<?php if (! empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <ul class="mb-0 ps-3">
            <?php foreach ($errors as $message): ?><li><?= esc($message) ?></li><?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
