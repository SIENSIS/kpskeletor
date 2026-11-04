<?php
/**
 * @var string      $name
 * @var string|null $imageUrl
 * @var string      $initials
 * @var string      $size   sm|md|lg
 * @var int         $colour 1–8 (class avatar-c<n>)
 */
?>
<?php if (! empty($imageUrl)): ?>
<img src="<?= esc(ui_safe_url($imageUrl)) ?>" alt="<?= esc($name) ?>" class="avatar avatar-<?= esc($size) ?> rounded-circle" loading="lazy">
<?php else: ?>
<span class="avatar avatar-<?= esc($size) ?> avatar-c<?= (int) $colour ?> rounded-circle" role="img" aria-label="<?= esc($name) ?>"><?= esc($initials) ?></span>
<?php endif ?>
